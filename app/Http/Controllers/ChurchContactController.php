<?php

namespace App\Http\Controllers;

use App\Models\ChurchContact;
use App\Models\User;
use Illuminate\Http\Request;

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
            ->with(['creator:id,name,email', 'department:id,name,is_greeting'])
            ->latest()
            ->get();

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $churchId = $user?->church_id;

        if (!$churchId || !$this->canCreateContacts($user)) {
            abort(403, 'Greeting department access required.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_sda' => ['required', 'boolean'],
        ]);

        $contact = ChurchContact::create([
            'church_id' => $churchId,
            'department_id' => $user->department_id,
            'created_by' => $user->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'is_sda' => $data['is_sda'],
        ]);

        return response()->json(
            $contact->load(['creator:id,name,email', 'department:id,name,is_greeting']),
            201
        );
    }

    private function canCreateContacts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return (bool) $user->department?->is_greeting;
    }

    private function canManageContacts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SECRETARY, User::ROLE_SUPERADMIN], true)
            || (bool) $user->department?->is_greeting;
    }
}
