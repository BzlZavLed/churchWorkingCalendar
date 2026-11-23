<?php
namespace App\Http\Controllers;
use App\Models\{Church,VisitorForm};
use App\Services\VisitorForms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VisitorFormController extends Controller
{
    public function logo(Church $church)
    {
        abort_unless($church->visitor_logo_path && Storage::disk('local')->exists($church->visitor_logo_path), 404);
        return response()->file(Storage::disk('local')->path($church->visitor_logo_path), ['Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function settingsUpload(Request $request) {
        abort_unless($request->user()?->isSecretary() && $request->user()->church_id,403);
        $request->attributes->set('church_logo_settings',true);
        return $this->updateLogo($request);
    }
    public function settingsRemove(Request $request) {
        abort_unless($request->user()?->isSecretary() && $request->user()->church_id,403);
        $request->attributes->set('church_logo_settings',true);
        return $this->removeLogo($request);
    }

    public function updateLogo(Request $request)
    {
        $user = $request->user();
        abort_unless(!$user->isSuperAdmin() && ($user->hasCapability('visitors.configure') || $request->attributes->get('church_logo_settings')) && $user->church_id, 403);
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);
        $path = $request->file('logo')->store('visitor-logos/'.$user->church_id, 'local');
        try {
            DB::transaction(function () use ($user, $path) {
                $church = Church::whereKey($user->church_id)->lockForUpdate()->firstOrFail();
                $old = $church->visitor_logo_path;
                $church->visitor_logo_path = $path;
                $church->save();
                if ($old) Storage::disk('local')->delete($old);
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return response()->json(['logo_url' => Church::findOrFail($user->church_id)->visitorLogoUrl()]);
    }

    public function alignLogo(Request $request)
    {
        $user = $request->user();
        abort_unless(!$user->isSuperAdmin() && ($user->hasCapability('visitors.configure') || $request->attributes->get('church_logo_settings')) && $user->church_id, 403);
        $data = $request->validate(['alignment' => ['required', 'in:left,center,right']]);
        $church = Church::findOrFail($user->church_id);
        $church->visitor_logo_alignment = $data['alignment'];
        $church->save();
        return response()->json(['logo_alignment' => $church->visitor_logo_alignment]);
    }

    public function removeLogo(Request $request)
    {
        $user = $request->user();
        abort_unless(!$user->isSuperAdmin() && ($user->hasCapability('visitors.configure') || $request->attributes->get('church_logo_settings')) && $user->church_id, 403);
        DB::transaction(function () use ($user) {
            $church = Church::whereKey($user->church_id)->lockForUpdate()->firstOrFail();
            $old = $church->visitor_logo_path;
            $church->visitor_logo_path = null;
            $church->save();
            if ($old) Storage::disk('local')->delete($old);
        });
        return response()->json(['logo_url' => null]);
    }

    public function show(Request $request, VisitorForms $forms)
    {
        $user=$request->user();
        abort_unless($user->church_id && (app(\App\Services\ModuleAccess::class)->read($user,'visitors') || app(\App\Services\ModuleAccess::class)->read($user,'greeting')),403);
        return $forms->definition($forms->current($user->church_id)) + ['logo_url' => Church::findOrFail($user->church_id)->visitorLogoUrl(), 'logo_alignment' => Church::findOrFail($user->church_id)->visitor_logo_alignment];
    }

    public function publish(Request $request, VisitorForms $forms)
    {
        $user=$request->user();
        abort_unless(!$user->isSuperAdmin() && ($user->hasCapability('visitors.configure') || $request->attributes->get('church_logo_settings')) && $user->church_id,403);
        $data=$request->validate([
            'base_version'=>['required','integer','min:0'],
            'fields'=>['present','array','max:40'],
            'fields.*'=>['array:id,type,label,required,options'],
            'fields.*.id'=>['required','uuid','distinct'],
            'fields.*.type'=>['required','in:text,textarea,number,date,select,checkbox,label'],
            'fields.*.label'=>['required','string','max:500'],
            'fields.*.required'=>['required','boolean'],
            'fields.*.options'=>['present','array','max:40'],
            'fields.*.options.*'=>['required','string','max:150'],
        ]);
        return DB::transaction(function() use($data,$forms,$user) {
            Church::whereKey($user->church_id)->lockForUpdate()->firstOrFail();
            $current=$forms->current($user->church_id);
            abort_unless(($current?->version ?? 0)===$data['base_version'],409,'Another version was published. Reload before editing.');
            $previous=VisitorForm::where('church_id',$user->church_id)->get()->flatMap(fn($f)=>$f->fields)->keyBy('id');
            foreach($data['fields'] as $field) {
                if (isset($previous[$field['id']]) && $previous[$field['id']]['type']!==$field['type']) {
                    throw ValidationException::withMessages(['fields'=>['Existing field types cannot change. Add a new field instead.']]);
                }
                if ($field['type']==='select' && (!$field['options'] || count(array_unique($field['options']))!==count($field['options']))) {
                    throw ValidationException::withMessages(['fields'=>['Dropdown choices must be nonempty and unique.']]);
                }
            }
            $fields=array_map(fn($f)=>array_merge($f,['required'=>$f['type']==='label'?false:$f['required'],'options'=>$f['type']==='select'?$f['options']:[]]),$data['fields']);
            $form=VisitorForm::create(['church_id'=>$user->church_id,'version'=>($current?->version ?? 0)+1,'fields'=>$fields,'published_by'=>$user->id]);
            return response()->json($forms->definition($form),201);
        });
    }
}
