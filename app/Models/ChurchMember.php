<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChurchMember extends Model {
    protected static function booted(): void {
        static::saving(function(self $member) {
            $church=Church::findOrFail($member->church_id);
            $data=$member->data??[];
            $data['OrgName']=$church->name;
            $data['OrgCode']=$church->organization_code;
            $member->data=$data;
        });
    }
    protected $guarded = ['id'];
    protected $casts = ['data'=>'array','missing_fields'=>'array','missing_data_authorization'=>'array'];
}
