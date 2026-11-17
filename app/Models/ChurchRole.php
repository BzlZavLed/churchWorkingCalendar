<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChurchRole extends Model {
 protected $guarded=['id'];
 protected $casts=['permissions'=>'array','capabilities'=>'array','is_builtin'=>'boolean'];
}
