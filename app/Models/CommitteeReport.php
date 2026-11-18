<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommitteeReport extends Model {
 protected $guarded=['id'];
 protected $hidden=['path'];
 protected $casts=['size'=>'integer'];
 public function uploader(){return $this->belongsTo(User::class,'uploaded_by');}
}
