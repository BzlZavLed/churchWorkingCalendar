<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Committee extends Model {
 protected $guarded=['id'];
 protected $casts=['formation_voted_on'=>'date:Y-m-d','revision'=>'integer'];
 public function members(){return $this->belongsToMany(ChurchMember::class,'committee_members');}
 public function reports(){return $this->hasMany(CommitteeReport::class)->latest('id');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
}
