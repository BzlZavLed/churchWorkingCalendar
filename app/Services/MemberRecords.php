<?php
namespace App\Services;
use App\Models\MemberTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class MemberRecords {
    public function current(int $church): MemberTemplate {
        return \Illuminate\Support\Facades\DB::transaction(function() use($church) {
            \App\Models\Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $current=MemberTemplate::where('church_id',$church)->orderByDesc('version')->first();
            $standard=MemberDirectorySchema::fields();
            if($current && collect($current->fields)->contains('id','BornOn')) return $current;
            // Keep old field definitions and values readable, but no longer require custom fields.
            $legacy=MemberTemplate::where('church_id',$church)->orderBy('version')->get()->flatMap(fn($t)=>$t->fields)->keyBy('id')->values()->map(fn($f)=>array_replace($f,['required'=>false,'group'=>'legacy']))->all();
            return MemberTemplate::create(['church_id'=>$church,'version'=>($current?->version ?? 0)+1,'fields'=>array_merge($standard,$legacy)]);
        });
    }
    public function validate(MemberTemplate $template, array $input, array $allowedMissing=[]): array {
        $baseValidator = Validator::make($input, [
            'name'=>[in_array('name',$allowedMissing)?'nullable':'required_without:given_name','nullable','string','max:255'], 'given_name'=>['sometimes',in_array('name',$allowedMissing)?'nullable':'required','string','max:80'], 'first_surname'=>['nullable','string','max:80'], 'second_surname'=>['nullable','string','max:80'], 'email'=>[in_array('email',$allowedMissing)?'nullable':'required','email','max:255'],
            'phone'=>[in_array('phone',$allowedMissing)?'nullable':'required','string','max:50','regex:/^\+?[0-9() .\-]{5,50}$/'],
            'data'=>['present','array','max:1000'],
        ]);
        $baseErrors=$baseValidator->errors()->toArray();
        $customInput=is_array($input['data'] ?? null)?$input['data']:[];
        $fields = collect($template->fields)->keyBy('id');
        if (array_diff(array_keys($customInput),$fields->keys()->all())) throw ValidationException::withMessages(['data'=>['Unknown template field. Reload the template.']]);
        $rules=[]; $names=[];
        foreach ($fields as $id=>$field) {
            $rules[$id]=array_merge([$field['required'] && !($id==='BornOn' && in_array('dob',$allowedMissing))?'required':'nullable'],match($field['type']) {
                'email'=>['email','max:255'], 'phone'=>['string','max:50','regex:/^\+?[0-9() .\-]{5,50}$/'],
                'dob'=>['date_format:Y-m-d','before_or_equal:today'], 'date'=>['date_format:Y-m-d'],
                'number'=>['numeric',match($id){'Latitude'=>'between:-90,90','Longitude'=>'between:-180,180',default=>'between:-1000000000,1000000000'}], 'checkbox'=>['boolean'],
                'select'=>['string',Rule::in($field['options'])],
                default=>['string','max:'.($field['type']==='textarea'?5000:500)],
            });
            $names[$id]=$field['label'];
        }
        $customValidator=Validator::make($customInput,$rules,[],$names);
        $allErrors=array_merge($baseErrors,$customValidator->errors()->toArray());
        if($allErrors) throw ValidationException::withMessages($allErrors);
        $base=$baseValidator->validated();
        $base['data']=$customValidator->validated();
        $base['email']=strtolower(trim($base['email']??''));
        if (!empty($base['given_name'])) {
            foreach (['given_name','first_surname','second_surname'] as $key) $base[$key]=trim($base[$key] ?? '');
            $base['name']=implode(' ',array_filter([$base['given_name'],$base['data']['MiddleName'] ?? '',$base['first_surname'],$base['second_surname'],$base['data']['Suffix'] ?? '']));
        }
        $base['name']=trim($base['name']??''); $base['phone']=trim($base['phone']??'');
        Validator::make($base,['name'=>[in_array('name',$allowedMissing)?'nullable':'required','string','max:255']])->validate();
        $base['missing_fields']=[];
        foreach(['name'=>$base['name'],'email'=>$base['email'],'phone'=>$base['phone'],'dob'=>$base['data']['BornOn']??null] as $key=>$value) if(blank($value)) $base['missing_fields'][]=$key;
        return $base;
    }
    public function headers(MemberTemplate $template): array {
        return MemberDirectorySchema::HEADERS;
    }
}
