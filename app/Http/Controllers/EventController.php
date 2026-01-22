<?php

namespace App\Http\Controllers;

use App\Events\EventCancelled;
use App\Events\EventCreated;
use App\Events\EventLocked;
use App\Events\EventUpdated;
use App\Events\HoldCreated;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventHistory;
use App\Models\EventNote;
use App\Models\Objective;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'department_id' => ['nullable', 'integer'],
            'objective_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'review_status' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($request->input('start'))->utc();
        $end = Carbon::parse($request->input('end'))->utc();

        $events = Event::query()
            ->with(['objective', 'department', 'histories.user', 'notes.author', 'notes.replyAuthor'])
            ->overlapping($start, $end)
            ->when(!$request->user()->isSuperAdmin(), function ($query) use ($request) {
                $query->whereHas('department', function ($departmentQuery) use ($request) {
                    $departmentQuery->where('church_id', $request->user()->church_id);
                });
            })
            ->when($request->filled('department_id'), function ($query) use ($request) {
                $query->where('department_id', $request->integer('department_id'));
            })
            ->when($request->filled('objective_id'), function ($query) use ($request) {
                $query->where('objective_id', $request->integer('objective_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('review_status'), function ($query) use ($request) {
                $query->where('review_status', $request->string('review_status'));
            })
            ->orderBy('start_at')
            ->get();

        return response()->json($events);
    }

    public function storeHold(Request $request)
    {
        $this->authorize('create', Event::class);

        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'objective_id' => ['nullable', 'exists:objectives,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ]);

        $startAt = Carbon::parse($data['start_at'])->utc();
        $endAt = Carbon::parse($data['end_at'])->utc();

        $departmentId = $this->resolveDepartmentId($request, $data['department_id'] ?? null);
        $objectiveId = $data['objective_id'] ?? null;
        if ($objectiveId !== null) {
            $this->assertObjectiveMatchesDepartment($objectiveId, $departmentId);
        }

        $event = DB::transaction(function () use ($request, $data, $startAt, $endAt, $departmentId, $objectiveId) {
            if (Event::hasConflict($startAt, $endAt)) {
                throw ValidationException::withMessages([
                    'start_at' => ['The selected time is already blocked.'],
                ]);
            }

            $event = Event::create([
                'department_id' => $departmentId,
                'objective_id' => $objectiveId,
                'title' => $data['title'] ?? 'Hold',
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'hold',
                'expires_at' => now()->addMinutes(5),
                'review_status' => 'pending',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            event(new HoldCreated($event));

            return $event;
        });

        return response()->json($event, 201);
    }

    public function lockEvent(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $locked = DB::transaction(function () use ($request, $event) {
            if ($event->status !== 'hold' || ($event->expires_at !== null && $event->expires_at->isPast())) {
                throw ValidationException::withMessages([
                    'status' => ['Hold is no longer valid.'],
                ]);
            }

            if (!$event->objective_id) {
                throw ValidationException::withMessages([
                    'objective_id' => ['Objective is required to confirm the event.'],
                ]);
            }

            if (Event::hasConflict($event->start_at, $event->end_at, $event->id)) {
                throw ValidationException::withMessages([
                    'start_at' => ['The selected time is already blocked.'],
                ]);
            }

            $event->update([
                'status' => 'locked',
                'expires_at' => null,
                'review_status' => 'pending',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'updated_by' => $request->user()->id,
            ]);

            event(new EventCreated($event));
            event(new EventLocked($event));

            return $event;
        });

        return response()->json($locked);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'objective_id' => ['sometimes', 'exists:objectives,id'],
        ]);

        $updatedEvent = DB::transaction(function () use ($request, $event, $data) {
            $original = [
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start_at' => $event->start_at?->toAtomString(),
                'end_at' => $event->end_at?->toAtomString(),
                'objective_id' => $event->objective_id,
            ];

            $startAt = array_key_exists('start_at', $data)
                ? Carbon::parse($data['start_at'])->utc()
                : $event->start_at;

            $endAt = array_key_exists('end_at', $data)
                ? Carbon::parse($data['end_at'])->utc()
                : $event->end_at;

            if ($endAt->lessThanOrEqualTo($startAt)) {
                throw ValidationException::withMessages([
                    'end_at' => ['End time must be after start time.'],
                ]);
            }

            if (Event::hasConflict($startAt, $endAt, $event->id)) {
                throw ValidationException::withMessages([
                    'start_at' => ['The selected time is already blocked.'],
                ]);
            }

            if (array_key_exists('objective_id', $data)) {
                $this->assertObjectiveMatchesDepartment($data['objective_id'], $event->department_id);
            }

            $event->update([
                'title' => $data['title'] ?? $event->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $event->description,
                'location' => array_key_exists('location', $data) ? $data['location'] : $event->location,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'objective_id' => $data['objective_id'] ?? $event->objective_id,
                'review_status' => 'pending',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'updated_by' => $request->user()->id,
            ]);

            $changes = [];
            $current = [
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start_at' => $event->start_at?->toAtomString(),
                'end_at' => $event->end_at?->toAtomString(),
                'objective_id' => $event->objective_id,
            ];

            foreach ($current as $field => $value) {
                if ($original[$field] !== $value) {
                    $changes[$field] = [
                        'from' => $original[$field],
                        'to' => $value,
                    ];
                }
            }

            if (!empty($changes)) {
                EventHistory::create([
                    'event_id' => $event->id,
                    'user_id' => $request->user()->id,
                    'action' => 'update',
                    'note' => null,
                    'changes' => $changes,
                ]);
            }

            event(new EventUpdated($event));

            return $event;
        });

        return response()->json($updatedEvent);
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorize('delete', $event);

        $cancelled = DB::transaction(function () use ($request, $event) {
            $event->update([
                'status' => 'cancelled',
                'updated_by' => $request->user()->id,
            ]);

            event(new EventCancelled($event));

            return $event;
        });

        return response()->json($cancelled);
    }

    public function review(Request $request, Event $event)
    {
        $this->authorize('review', $event);

        $data = $request->validate([
            'review_status' => ['required', 'in:approved,denied,changes_requested'],
            'review_note' => ['nullable', 'string'],
            'publish_to_feed' => ['nullable', 'boolean'],
            'accepted_at' => ['nullable', 'date'],
        ]);

        if ($event->status !== 'locked') {
            throw ValidationException::withMessages([
                'status' => ['Only locked events can be reviewed.'],
            ]);
        }

        if (in_array($data['review_status'], ['denied', 'changes_requested'], true) && empty($data['review_note'])) {
            throw ValidationException::withMessages([
                'review_note' => ['A note is required for this review status.'],
            ]);
        }

        $reviewed = DB::transaction(function () use ($request, $event, $data) {
            $finalValidation = match ($data['review_status']) {
                'approved' => 'accepted',
                'denied' => 'rejected',
                'changes_requested' => 'update_requested',
                default => null,
            };

            $previousReviewStatus = $event->review_status;
            $previousFinalValidation = $event->final_validation;
            $previousPublishToFeed = $event->publish_to_feed;
            $previousAcceptedAt = $event->accepted_at;

            $shouldPublish = $data['review_status'] === 'approved'
                && ($data['publish_to_feed'] ?? false) === true;

            $acceptedAt = null;
            if ($finalValidation === 'accepted') {
                $acceptedAt = !empty($data['accepted_at'])
                    ? Carbon::parse($data['accepted_at'])
                    : now();
            }

            $event->update([
                'review_status' => $data['review_status'],
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'final_validation' => $finalValidation,
                'accepted_at' => $acceptedAt,
                'publish_to_feed' => $shouldPublish,
                'published_at' => $shouldPublish ? now() : null,
                'requires_club_review' => false,
            ]);

            EventHistory::create([
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'action' => 'review',
                'note' => $data['review_note'] ?? null,
                'changes' => [
                    'review_status' => [
                        'from' => $previousReviewStatus,
                        'to' => $data['review_status'],
                    ],
                    'final_validation' => [
                        'from' => $previousFinalValidation,
                        'to' => $finalValidation,
                    ],
                    'accepted_at' => [
                        'from' => $previousAcceptedAt,
                        'to' => $acceptedAt,
                    ],
                    'publish_to_feed' => [
                        'from' => $previousPublishToFeed,
                        'to' => $shouldPublish,
                    ],
                ],
            ]);

            event(new EventUpdated($event));

            return $event->load(['department', 'objective', 'histories.user', 'notes.author', 'notes.replyAuthor']);
        });

        return response()->json($reviewed);
    }

    public function publishAccepted(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSecretary()) {
            abort(403);
        }

        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $start = Carbon::parse($data['start'])->utc();
        $end = Carbon::parse($data['end'])->utc();

        $events = Event::query()
            ->whereHas('department', function ($query) use ($user) {
                $query->where('church_id', $user->church_id);
            })
            ->where('final_validation', 'accepted')
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->get();

        $updated = 0;

        DB::transaction(function () use ($events, $user, &$updated) {
            foreach ($events as $event) {
                if ($event->publish_to_feed) {
                    continue;
                }

                $event->update([
                    'publish_to_feed' => true,
                    'published_at' => now(),
                    'updated_by' => $user->id,
                ]);

                EventHistory::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'action' => 'publish_batch',
                    'note' => 'Publicacion masiva de eventos aceptados.',
                    'changes' => [
                        'publish_to_feed' => [
                            'from' => false,
                            'to' => true,
                        ],
                        'published_at' => [
                            'from' => null,
                            'to' => $event->published_at?->toAtomString(),
                        ],
                    ],
                ]);

                $updated++;
            }
        });

        return response()->json([
            'updated' => $updated,
        ]);
    }

    private function resolveDepartmentId(Request $request, ?int $departmentId): int
    {
        /** @var User $user */
        $user = $request->user();

        $resolved = $departmentId ?? $user->department_id;

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'department_id' => ['Department is required for event creation.'],
            ]);
        }

        if (!$user->isSuperAdmin() && !$user->isAdmin() && $resolved !== $user->department_id) {
            throw ValidationException::withMessages([
                'department_id' => ['You cannot create events for other departments.'],
            ]);
        }

        if (!$user->isSuperAdmin()) {
            $department = Department::find($resolved);
            if ($department === null || $department->church_id !== $user->church_id) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department must belong to your church.'],
                ]);
            }
        }

        return $resolved;
    }

    private function assertObjectiveMatchesDepartment(int $objectiveId, int $departmentId): void
    {
        $objectiveDepartmentId = Objective::whereKey($objectiveId)->value('department_id');
        if ($objectiveDepartmentId !== $departmentId) {
            throw ValidationException::withMessages([
                'objective_id' => ['Objective must belong to the selected department.'],
            ]);
        }
    }
}
