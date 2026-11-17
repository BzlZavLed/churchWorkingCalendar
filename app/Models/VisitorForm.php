<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VisitorForm extends Model
{
    protected $fillable = ['church_id','version','fields','published_by'];
    protected $casts = ['fields'=>'array'];
}
