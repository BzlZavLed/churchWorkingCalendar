<?php

namespace App\Http\Controllers;

use App\Models\ChurchContact;
use App\Models\Church;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChurchContactController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $churchId = $user?->church_id;

        if (!$churchId || !$this->canManageContacts($user)) {
            abort(403, 'Church contact access required.');
        }

        $contacts = ChurchContact::query()
            ->where('church_id', $churchId)
            ->with(['creator:id,name,email', 'department:id,name,is_greeting', 'visitorForm:id,version,fields'])
            ->latest()
            ->get();

        if ($user->isPastor() || !app(\App\Services\ModuleAccess::class)->write($user,'visitors')) $contacts->each->makeHidden('consent_token');
        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $churchId = $user?->church_id;

        if (!$churchId || !($user->hasCapability('greeting.register') || $this->canCreateContacts($user))) {
            abort(403, 'Greeting department access required.');
        }

        return $this->createContact($request, $churchId, $user->department_id, $user->id);
    }

    public function publicForm(Church $church)
    {
        return response()->json(['name' => $church->name, 'logo_url' => $church->visitorLogoUrl(), 'logo_alignment' => $church->visitor_logo_alignment, 'form' => app(\App\Services\VisitorForms::class)->definition(app(\App\Services\VisitorForms::class)->current($church->id))]);
    }

    public function publicStore(Request $request, Church $church)
    {
        $this->createContact($request, $church->id, null, null);
        return response()->json(['message' => 'Visitor registered.'], 201);
    }

    private function createContact(Request $request, int $churchId, ?int $departmentId, ?int $creatorId)
    {
        $data = $request->validate([
            'visitor_form_id' => ['nullable', 'integer'],
            'custom_answers' => ['nullable', 'array', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_sda' => ['required', 'boolean'],
            'sms_consent' => ['required', 'boolean'],
            'email_consent' => ['required', 'boolean'],
        ]);

        if (($data['sms_consent'] ?? false) && empty($data['phone'])) {
            throw ValidationException::withMessages([
                'sms_consent' => ['Phone number is required for SMS consent.'],
            ]);
        }

        if (($data['email_consent'] ?? false) && empty($data['email'])) {
            throw ValidationException::withMessages([
                'email_consent' => ['Email is required for email consent.'],
            ]);
        }

        $custom = app(\App\Services\VisitorForms::class)->answers($churchId, $request->only(['visitor_form_id','custom_answers']));
        $contact = ChurchContact::create($custom + [
            'church_id' => $churchId,
            'department_id' => $departmentId,
            'created_by' => $creatorId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'is_sda' => $data['is_sda'],
            'sms_consent' => $data['sms_consent'],
            'sms_consented_at' => $data['sms_consent'] ? now() : null,
            'email_consent' => $data['email_consent'],
            'email_consented_at' => $data['email_consent'] ? now() : null,
        ]);

        return response()->json(
            $contact->load(['creator:id,name,email', 'department:id,name,is_greeting', 'visitorForm:id,version,fields']),
            201
        );
    }

    public function update(Request $request, ChurchContact $contact, \App\Services\VisitorForms $forms)
    {
        $user = $request->user();
        abort_unless($user?->hasCapability('visitors.edit') && $user->church_id, 403);
        abort_unless($contact->church_id === $user->church_id, 404);
        $data = $request->validate([
            'visitor_form_id' => ['nullable','integer'],
            'custom_answers' => ['sometimes','array','max:40'],
            'sms_consent' => ['sometimes','boolean'],
            'email_consent' => ['sometimes','boolean'],
            'consent_confirmed' => ['sometimes','boolean'],
        ]);
        $answers = null;
        if (array_key_exists('custom_answers', $data)) {
            if (($forms->current($user->church_id)?->id) !== ($data['visitor_form_id'] ?? null)) {
                throw ValidationException::withMessages(['visitor_form_id'=>['The form changed. Reload before editing.']]);
            }
            $answers = $forms->answers($user->church_id, $data, true)['custom_answers'];
        }
        return \Illuminate\Support\Facades\DB::transaction(function () use ($contact, $data, $answers) {
            $contact = ChurchContact::whereKey($contact->id)->lockForUpdate()->firstOrFail();
            foreach (['sms'=>'phone','email'=>'email'] as $channel=>$field) {
                $key = $channel.'_consent';
                if (!array_key_exists($key,$data)) continue;
                if ($data[$key] && !$contact->$key) {
                    if (empty($contact->$field) || empty($data['consent_confirmed'])) {
                        throw ValidationException::withMessages([$key=>['Confirm visitor permission and provide contact information before enabling messaging.']]);
                    }
                    $contact->{$channel.'_consented_at'} = now();
                } elseif (!$data[$key]) $contact->{$channel.'_consented_at'} = null;
                $contact->$key = $data[$key];
            }
            if (isset($answers)) {
                if ($contact->original_custom_answers === null) $contact->original_custom_answers = $contact->custom_answers ?? [];
                $contact->custom_answers = array_replace($contact->custom_answers ?? [], $answers);
            }
            $contact->save();
            return response()->json($contact->load(['creator:id,name,email','department:id,name,is_greeting','visitorForm:id,version,fields'])->makeHidden('consent_token'));
        });
    }

    public function restoreConsent(Request $request, ChurchContact $contact)
    {
        $user = $request->user();
        abort_unless($user?->church_id && $this->canCreateContacts($user), 403);
        abort_unless($contact->church_id === $user->church_id, 404);
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email'],
            'consent_confirmed' => ['required', 'accepted'],
        ]);
        $channel = $data['channel'];
        $field = $channel === 'sms' ? 'phone' : 'email';
        if (empty($contact->{$field})) {
            throw ValidationException::withMessages([$field => ['Contact information is required to enable this permission.']]);
        }
        if (!$contact->{$channel.'_consent'}) {
            $contact->update([$channel.'_consent' => true, $channel.'_consented_at' => now()]);
        }
        return response()->json($contact->load(['creator:id,name,email', 'department:id,name,is_greeting', 'visitorForm:id,version,fields']));
    }

    private function canCreateContacts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasCapability('visitors.contact');
    }

    private function canManageContacts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return app(\App\Services\ModuleAccess::class)->read($user, 'visitors');
    }
}
