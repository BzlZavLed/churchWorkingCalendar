<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingPoint extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_NEEDS_UPDATE = 'needs_update';

    public const FINAL_APPROVED = 'approved';
    public const FINAL_DENIED = 'denied';
    public const FINAL_INFORMATIVE = 'informative';
    public const FINAL_POSTPONED = 'postponed';
    public const FINAL_NEEDS_UPDATE = 'needs_update';

    protected $fillable = [
        'meeting_id',
        'department_id',
        'related_meeting_point_id',
        'title',
        'description',
        'status',
        'agenda_order',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'final_status',
        'final_note',
        'finalized_by',
        'finalized_at',
        'active_at',
        'resubmitted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'active_at' => 'datetime',
        'resubmitted_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function relatedPoint(): BelongsTo
    {
        return $this->belongsTo(MeetingPoint::class, 'related_meeting_point_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingPointNote::class, 'meeting_point_id');
    }
}
