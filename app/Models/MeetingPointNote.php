<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingPointNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_point_id',
        'note',
        'created_by',
    ];

    public function point(): BelongsTo
    {
        return $this->belongsTo(MeetingPoint::class, 'meeting_point_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
