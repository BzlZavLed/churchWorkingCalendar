<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'meeting_date',
        'planned_start_at',
        'start_at',
        'end_at',
        'location',
        'agenda_closed_at',
        'summary_generated',
        'summary_text',
        'summary_public',
        'active_meeting_point_id',
        'opened_by',
        'opening_prayer',
        'opening_remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'planned_start_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'agenda_closed_at' => 'datetime',
        'summary_public' => 'boolean',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(MeetingPoint::class);
    }

    public function meetingNotes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public function activePoint(): BelongsTo
    {
        return $this->belongsTo(MeetingPoint::class, 'active_meeting_point_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deadlineAt(): CarbonInterface
    {
        return $this->planned_start_at->copy()->subDay();
    }
}
