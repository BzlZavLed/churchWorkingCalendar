<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceService extends Model {protected $guarded=['id'];protected $casts=['active'=>'boolean','weekday'=>'integer'];}
