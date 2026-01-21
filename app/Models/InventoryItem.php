<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'quantity',
        'value',
        'total_value',
        'description',
        'brand',
        'model',
        'serial_number',
        'location',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'value' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
