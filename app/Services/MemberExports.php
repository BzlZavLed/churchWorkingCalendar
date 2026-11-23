<?php
namespace App\Services;

use App\Models\ChurchMember;

class MemberExports
{
    public function columns(int $church): array
    {
        $base = [
            ['id'=>'name','label'=>'Nombre completo'],
            ['id'=>'given_name','label'=>'Nombre'],
            ['id'=>'first_surname','label'=>'Primer apellido'],
            ['id'=>'second_surname','label'=>'Segundo apellido'],
            ['id'=>'email','label'=>'Correo'],
            ['id'=>'phone','label'=>'Teléfono principal'],
        ];
        foreach (app(MemberRecords::class)->current($church)->fields as $field) {
            $base[]=['id'=>'data.'.$field['id'],'label'=>$field['label']];
        }
        return $base;
    }

    public function dob(ChurchMember $member, array $fields): ?string
    {
        if (!empty($member->data['BornOn'])) return $member->data['BornOn'];
        foreach ($fields as $field) {
            if ($field['type']==='dob' && !empty($member->data[$field['id']])) return $member->data[$field['id']];
        }
        return null;
    }

    public function rawValue(ChurchMember $member, string $column, array $fields): mixed
    {
        $value = $column==='data.BornOn' ? $this->dob($member,$fields)
            : (str_starts_with($column,'data.') ? ($member->data[substr($column,5)] ?? '') : $member->getAttribute($column));
        if (is_bool($value)) $value=$value?'Sí':'No';
        if (is_array($value)) $value=implode(', ', $value);
        return $value;
    }

    public function value(ChurchMember $member, string $column, array $fields): string
    {
        $value=(string)$this->rawValue($member,$column,$fields);
        // CSV cells must remain text when opened in spreadsheet software.
        return preg_match('/^[\s]*[=+@\-]/u',$value) || preg_match('/^[\t\r\n]/',$value) ? "'".$value : $value;
    }
}
