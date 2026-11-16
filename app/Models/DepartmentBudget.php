<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentBudget extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['opening_cents' => 'integer'];

    public function department() { return $this->belongsTo(Department::class); }
    public function entries() { return $this->hasMany(BudgetEntry::class); }
}
