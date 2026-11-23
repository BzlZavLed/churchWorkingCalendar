<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MemberImportDraft extends Model {
 protected $guarded=['id'];
 protected $casts=['rows'=>'encrypted:array'];
}
