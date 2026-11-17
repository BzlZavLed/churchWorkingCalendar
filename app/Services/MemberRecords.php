<?php
namespace App\Services;
use App\Models\MemberTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class MemberRecords {
    public function current(int $church): ?MemberTemplate {
        return MemberTemplate::where('church_id',$church)->orderByDesc('version')->first();
    }
    public function validate(MemberTemplate $template, array $input): array {
        $base = Validator::make($input, [
            'name'=>['required','string','max:255'], 'email'=>['required','email','max:255'],
            'phone'=>['required','string','max:50','regex:/^\+?[0-9() .\-]{5,50}$/'],
            'data'=>['present','array','max:40'],
        ])->validate();
        $fields = collect($template->fields)->keyBy('id');
        if (array_diff(array_keys($base['data']),$fields->keys()->all())) throw ValidationException::withMessages(['data'=>['Unknown template field. Reload the template.']]);
        $rules=[]; $names=[];
        foreach ($fields as $id=>$field) {
            $rules[$id]=array_merge([$field['required']?'required':'nullable'],match($field['type']) {
                'email'=>['email','max:255'], 'phone'=>['string','max:50','regex:/^\+?[0-9() .\-]{5,50}$/'],
                'dob'=>['date_format:Y-m-d','before_or_equal:today'], 'date'=>['date_format:Y-m-d'],
                'number'=>['numeric','between:-1000000000,1000000000'], 'checkbox'=>['boolean'],
                'select'=>['string',Rule::in($field['options'])],
                default=>['string','max:'.($field['type']==='textarea'?5000:500)],
            });
            $names[$id]=$field['label'];
        }
        $base['data']=Validator::make($base['data'],$rules,[],$names)->validate();
        $base['email']=strtolower(trim($base['email']));
        $base['name']=trim($base['name']); $base['phone']=trim($base['phone']);
        return $base;
    }
    public function headers(MemberTemplate $template): array {
        return array_merge(['Name','Email','Phone'],array_column($template->fields,'label'));
    }
}
