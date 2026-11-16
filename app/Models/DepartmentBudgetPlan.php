<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentBudgetPlan extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['year' => 'integer', 'expected_cents' => 'integer'];
}
