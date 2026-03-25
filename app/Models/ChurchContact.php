<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'department_id',
        'created_by',
        'name',
        'phone',
        'email',
        'address',
        'is_sda',
    ];

    protected $casts = [
        'is_sda' => 'boolean',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
