<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceSession extends Model {protected $guarded=['id'];protected $casts=['starts_at'=>'immutable_datetime','ends_at'=>'immutable_datetime','opened_at'=>'immutable_datetime','closed_at'=>'immutable_datetime'];}
