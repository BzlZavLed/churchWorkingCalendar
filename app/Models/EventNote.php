<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'note',
        'reply',
        'reply_user_id',
        'replied_at',
        'seen_note',
        'seen_at',
        'seen_by_user_id',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
        'seen_note' => 'boolean',
        'seen_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_user_id');
    }
}
