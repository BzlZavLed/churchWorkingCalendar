<?php
namespace App\Services;

class MemberDirectorySchema
{
    public const FAMILY_POSITIONS = ['Primary','Spouse','Son','Daughter'];

    public const HEADERS = ['PersonId','Recipient','FirstName','MiddleName','LastName','Salutation','Suffix','FamilyId','FamilyPosition','OptionalLine','AddressLine','City','State','PostalCode','Country','IsConfidential','NondeliveryDescription','OrgCode','OrgName','RegionDescription','Email','FamilyPhone','IsUnlisted','WorkPhone','MobilePhone','MobileUnlisted','Latitude','Longitude','BornOn','EthnicGroupDescription'];

    public static function fields(): array
    {
        $groups = [
            'identity'=>['PersonId'=>'ID de persona','Recipient'=>'Destinatario','MiddleName'=>'Segundo nombre','Salutation'=>'Tratamiento','Suffix'=>'Sufijo','BornOn'=>'Fecha de nacimiento','EthnicGroupDescription'=>'Grupo étnico'],
            'family'=>['FamilyId'=>'ID de familia','FamilyPosition'=>'Posición en la familia'],
            'contact'=>['FamilyPhone'=>'Teléfono familiar','WorkPhone'=>'Teléfono de trabajo','MobilePhone'=>'Teléfono móvil','IsUnlisted'=>'Teléfono familiar privado','MobileUnlisted'=>'Teléfono móvil privado'],
            'address'=>['OptionalLine'=>'Línea adicional','AddressLine'=>'Dirección','City'=>'Ciudad','State'=>'Estado','PostalCode'=>'Código postal','Country'=>'País','Latitude'=>'Latitud','Longitude'=>'Longitud','IsConfidential'=>'Dirección confidencial','NondeliveryDescription'=>'Motivo de no entrega'],
            'church'=>['OrgCode'=>'Código de organización','OrgName'=>'Organización','RegionDescription'=>'Región'],
        ];
        $result=[];
        foreach($groups as $group=>$fields) foreach($fields as $key=>$label) {
            $type=match($key) {'BornOn'=>'dob','IsUnlisted','MobileUnlisted','IsConfidential'=>'checkbox','Latitude','Longitude'=>'number','FamilyPhone','WorkPhone','MobilePhone'=>'phone',default=>'text'};
            $result[]=['id'=>$key,'label'=>$label,'type'=>$type,'required'=>$key==='BornOn','options'=>[],'group'=>$group];
        }
        return $result;
    }
}
