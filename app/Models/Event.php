<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'objective_id',
        'title',
        'description',
        'location',
        'start_at',
        'end_at',
        'status',
        'expires_at',
        'review_status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'final_validation',
        'accepted_at',
        'publish_to_feed',
        'published_at',
        'external_source',
        'external_id',
        'is_club_event',
        'club_type',
        'plan_name',
        'requires_club_review',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'publish_to_feed' => 'boolean',
        'published_at' => 'datetime',
        'accepted_at' => 'datetime',
        'is_club_event' => 'boolean',
        'requires_club_review' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EventHistory::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EventNote::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOverlapping(Builder $query, CarbonInterface $startAt, CarbonInterface $endAt): Builder
    {
        return $query->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);
    }

    public function scopeActiveBlocking(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', 'locked')
                ->orWhere(function (Builder $query) {
                    $query->where('status', 'hold')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now());
                });
        });
    }

    public static function hasConflict(CarbonInterface $startAt, CarbonInterface $endAt, ?int $ignoreId = null): bool
    {
        $query = static::query()->overlapping($startAt, $endAt)->activeBlocking();

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
