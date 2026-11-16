<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHistory extends Model
{
    use HasFactory;

    protected $with = ['eventNote'];

    public function eventNote(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EventNote::class, 'event_history_id');
    }

    public function getNoteAttribute($legacy): ?string
    {
        return $this->eventNote?->note ?? $legacy;
    }

    protected $fillable = [
        'event_id',
        'user_id',
        'action',
        'note',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
