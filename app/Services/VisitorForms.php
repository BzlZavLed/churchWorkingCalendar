<?php
namespace App\Services;
use App\Models\VisitorForm;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisitorForms
{
    public function current(int $church): ?VisitorForm
    {
        return VisitorForm::where('church_id',$church)->orderByDesc('version')->first();
    }

    public function definition(?VisitorForm $form): array
    {
        return ['id'=>$form?->id, 'version'=>$form?->version ?? 0, 'fields'=>$form?->fields ?? [], 'layout'=>$form?->layout];
    }

    public function answers(int $church, array $input, bool $partial = false): array
    {
        $version = $input['visitor_form_id'] ?? null;
        $form = $version ? VisitorForm::where('church_id',$church)->find($version) : null;
        if (($version && !$form) || (!$version && $this->current($church))) {
            throw ValidationException::withMessages(['visitor_form_id'=>['Reload the visitor form before submitting.']]);
        }
        $answers=$input['custom_answers'] ?? [];
        if (!is_array($answers)) throw ValidationException::withMessages(['custom_answers'=>['Answers must be an object.']]);
        $fields=collect($form?->fields ?? [])->where('type','!=','label')->keyBy('id');
        if (array_diff(array_keys($answers),$fields->keys()->all())) throw ValidationException::withMessages(['custom_answers'=>['Unknown form field.']]);
        $rules=[]; $names=[];
        foreach ($fields as $id=>$field) {
            $required = !$partial && $field['required'];
            $rule=$partial ? ['sometimes','nullable'] : [$required ? 'required' : 'nullable'];
            $rule=array_merge($rule,match($field['type']) {
                'checkbox'=>$required ? ['boolean','accepted'] : ['boolean'], 'number'=>['numeric','between:-1000000000,1000000000'],
                'date'=>['date_format:Y-m-d'], 'select'=>['string',Rule::in($field['options'])],
                default=>['string','max:'.($field['type']==='textarea' ? '5000':'500')],
            });
            $rules[$id]=$rule; $names[$id]=$field['label'];
        }
        $validated=Validator::make($answers,$rules,[],$names)->validate();
        return ['visitor_form_id'=>$form?->id,'custom_answers'=>$validated];
    }
}
