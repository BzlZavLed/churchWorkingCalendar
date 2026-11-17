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
        'church_id',
        'is_conference_event',
        'open_requested_at',
        'department_id',
        'objective_id',
        'objective_name_snapshot',
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
        'is_conference_event' => 'boolean',
        'open_requested_at' => 'datetime',
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

    // review_status is authoritative; legacy output fields stay synchronized here.
    public function transitionReview(string $state, User $actor, string $action, ?string $note = null, array $changes = []): void
    {
        $previous = $this->review_status;
        $approved = $state === 'approved';
        $this->update([
            'review_status' => $state,
            'final_validation' => match ($state) {
                'approved' => 'accepted',
                'denied' => 'rejected',
                'changes_requested' => 'update_requested',
                default => null,
            },
            'review_note' => null,
            'reviewed_by' => in_array($state, ['approved', 'denied', 'changes_requested']) ? $actor->id : null,
            'reviewed_at' => in_array($state, ['approved', 'denied', 'changes_requested']) ? now() : null,
            'accepted_at' => $approved ? now() : null,
            'publish_to_feed' => $approved,
            'published_at' => $approved ? now() : null,
            'open_requested_at' => null,
            'updated_by' => $actor->id,
        ]);
        $this->recordAction($actor, $action, $note, array_merge($changes, [
            'review_status' => ['from' => $previous, 'to' => $state],
        ]));
    }

    public function recordAction(User $actor, string $action, ?string $note = null, array $changes = []): void
    {
        $history = $this->histories()->create([
            'user_id' => $actor->id, 'action' => $action, 'changes' => $changes,
        ]);
        if ($note !== null && trim($note) !== '') {
            $this->notes()->create([
                'user_id' => $actor->id, 'note' => trim($note), 'kind' => $action,
                'event_history_id' => $history->id, 'author_role' => $actor->role, 'author_is_reviewer' => $actor->hasCapability('calendar.review'),
                'seen_note' => false,
            ]);
        }
    }

    public function getReviewNoteAttribute($legacy): ?string
    {
        if (!in_array($this->review_status, ['approved', 'denied', 'changes_requested'], true)) return null;
        return $this->histories()->whereIn('action', ['accepted', 'rejected', 'changes_requested', 'reopened', 'review'])->latest('id')->first()?->note ?? $legacy;
    }

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

    public function scopeForChurch(Builder $query, int $churchId): Builder
    {
        return $query->where(fn ($q) => $q->where('church_id', $churchId)
            ->orWhereHas('department', fn ($d) => $d->where('church_id', $churchId)));
    }

    public function churchId(): ?int
    {
        return $this->church_id ?? $this->department?->church_id;
    }

    public function scopeOverlapping(Builder $query, CarbonInterface $startAt, CarbonInterface $endAt): Builder
    {
        return $query->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);
    }

    public function scopeActiveBlocking(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', 'locked')->where('review_status', '!=', 'denied')
                ->orWhere(function (Builder $query) {
                    $query->where('status', 'hold')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now());
                });
        });
    }

    public static function hasConflict(CarbonInterface $startAt, CarbonInterface $endAt, ?int $ignoreId = null, ?int $churchId = null): bool
    {
        $query = static::query()->overlapping($startAt, $endAt)->activeBlocking();

        if ($churchId !== null) {
            $query->forChurch($churchId);
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
