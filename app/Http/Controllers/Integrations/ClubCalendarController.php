<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventHistory;
use App\Models\Invitation;
use App\Models\Objective;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClubCalendarController extends Controller
{
    public function catalog(Request $request)
    {
        $data = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $invitation = Invitation::query()
            ->where('code', $data['invite_code'])
            ->first();

        if (!$invitation) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $isActive = $invitation->revoked_at === null
            && ($invitation->expires_at === null || $invitation->expires_at->isFuture())
            && $invitation->uses_count < $invitation->max_uses;

        if (!$isActive) {
            return response()->json(['status' => 'inactive'], 403);
        }

        $church = $invitation->church()->firstOrFail();

        $departments = Department::query()
            ->where('church_id', $church->id)
            ->where('is_club', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'user_name', 'is_club']);

        $objectives = Objective::query()
            ->whereHas('department', function ($query) use ($church) {
                $query->where('church_id', $church->id);
            })
            ->whereIn('department_id', $departments->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'department_id', 'name', 'description', 'evaluation_metrics']);

        return response()->json([
            'status' => 'active',
            'church' => [
                'id' => $church->id,
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'church_slug' => $church->slug,
            'departments' => $departments,
            'objectives' => $objectives,
        ]);
    }

    public function importCalendar(Request $request)
    {
        Log::info('Club calendar import payload received.', [
            'payload' => $request->all(),
        ]);

        $data = $request->validate([
            'church_slug' => ['required', 'string'],
            'calendar_year' => ['required', 'integer'],
            'timezone' => ['nullable', 'string'],
            'club_type' => ['nullable', 'string'],
            'plan_name' => ['nullable', 'string'],
            'publish_to_feed' => ['nullable', 'boolean'],
            'events' => ['required', 'array'],
            'events.*.external_id' => ['required', 'string'],
            'events.*.title' => ['required', 'string', 'max:255'],
            'events.*.description' => ['nullable', 'string'],
            'events.*.location' => ['nullable', 'string', 'max:255'],
            'events.*.start_at' => ['required', 'date'],
            'events.*.end_at' => ['required', 'date'],
            'events.*.department_id' => ['required', 'integer'],
            'events.*.objective_id' => ['required', 'integer'],
            'events.*.is_special' => ['nullable', 'boolean'],
            'events.*.club_type' => ['nullable', 'string'],
            'events.*.plan_name' => ['nullable', 'string'],
        ]);

        $church = Church::query()
            ->where('slug', $data['church_slug'])
            ->firstOrFail();

        $importUser = $this->resolveImportUser($church->id);
        if (!$importUser) {
            return response()->json([
                'message' => 'No import user available for this church.',
            ], 422);
        }

        $timezone = $data['timezone'] ?? null;
        $publishToFeed = $data['publish_to_feed'] ?? true;
        $defaultClubType = $data['club_type'] ?? null;
        $defaultPlanName = $data['plan_name'] ?? null;

        $conflicts = [];
        $imported = 0;
        $skipped = 0;
        $successes = [];

        foreach ($data['events'] as $payload) {
            $startAt = $this->parseUtc($payload['start_at'], $timezone);
            $endAt = $this->parseUtc($payload['end_at'], $timezone);

            if ($endAt->lessThanOrEqualTo($startAt)) {
                $conflicts[] = $this->buildErrorConflict($payload, 'invalid_range', 'End time must be after start time.');
                $skipped++;
                continue;
            }

            $department = Department::query()
                ->where('id', $payload['department_id'])
                ->where('church_id', $church->id)
                ->first();

            if (!$department) {
                $conflicts[] = $this->buildErrorConflict($payload, 'invalid_department', 'Department does not belong to church.');
                $skipped++;
                continue;
            }

            $objective = Objective::query()
                ->where('id', $payload['objective_id'])
                ->where('department_id', $department->id)
                ->first();

            if (!$objective) {
                $conflicts[] = $this->buildErrorConflict($payload, 'invalid_objective', 'Objective does not belong to department.');
                $skipped++;
                continue;
            }

            $existing = Event::query()
                ->where('external_source', 'clubs')
                ->where('external_id', $payload['external_id'])
                ->first();

            $overlapping = Event::query()
                ->whereHas('department', function ($query) use ($church) {
                    $query->where('church_id', $church->id);
                })
                ->overlapping($startAt, $endAt)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) {
                    $query->where('status', '!=', 'hold')
                        ->orWhere(function ($holdQuery) {
                            $holdQuery->where('status', 'hold')
                                ->where(function ($expireQuery) {
                                    $expireQuery->whereNull('expires_at')
                                        ->orWhere('expires_at', '>', now());
                                });
                        });
                })
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                ->get();

            $clubConflicts = $overlapping->where('is_club_event', true);
            $nonClubConflicts = $overlapping->where('is_club_event', false);
            $isSpecial = !empty($payload['is_special']);

            if ($isSpecial) {
                if ($overlapping->isNotEmpty()) {
                    $this->flagConflicts($overlapping, $importUser, $payload);
                    $conflicts[] = $this->buildOverlapConflict($payload, 'overlap', $overlapping);
                    $skipped++;
                    continue;
                }
            } else {
                if ($clubConflicts->isNotEmpty()) {
                    $this->flagConflicts($clubConflicts, $importUser, $payload);
                    $conflicts[] = $this->buildOverlapConflict($payload, 'club_overlap', $clubConflicts);
                    $skipped++;
                    continue;
                }
            }

            $event = DB::transaction(function () use (
                $payload,
                $startAt,
                $endAt,
                $department,
                $objective,
                $importUser,
                $existing,
                $publishToFeed,
                $defaultClubType,
                $defaultPlanName,
                $isSpecial,
                $nonClubConflicts,
                &$imported
            ) {
                $reviewStatus = $isSpecial ? 'pending' : 'approved';
                $finalValidation = $isSpecial ? null : 'accepted';
                $acceptedAt = $isSpecial ? null : now();
                $shouldPublish = !$isSpecial && $publishToFeed;

                $data = [
                    'department_id' => $department->id,
                    'objective_id' => $objective->id,
                    'title' => $payload['title'],
                    'description' => $payload['description'] ?? null,
                    'location' => $payload['location'] ?? null,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => 'locked',
                    'review_status' => $reviewStatus,
                    'review_note' => $isSpecial ? null : null,
                    'reviewed_by' => $importUser->id,
                    'reviewed_at' => now(),
                    'final_validation' => $finalValidation,
                    'accepted_at' => $acceptedAt,
                    'publish_to_feed' => $shouldPublish,
                    'published_at' => $shouldPublish ? now() : null,
                    'external_source' => 'clubs',
                    'external_id' => $payload['external_id'],
                    'is_club_event' => true,
                    'club_type' => $payload['club_type'] ?? $defaultClubType,
                    'plan_name' => $payload['plan_name'] ?? $defaultPlanName,
                    'updated_by' => $importUser->id,
                ];

                if (!$isSpecial && $nonClubConflicts->isNotEmpty()) {
                    $this->flagConflicts($nonClubConflicts, $importUser, $payload);
                }

                if ($existing) {
                    $existing->update($data);
                    $event = $existing->fresh();
                } else {
                    $data['created_by'] = $importUser->id;
                    $event = Event::create($data);
                }

                EventHistory::create([
                    'event_id' => $event->id,
                    'user_id' => $importUser->id,
                    'action' => $isSpecial ? 'import_special' : 'import',
                    'note' => $isSpecial
                        ? 'Evento especial importado. Requiere revision.'
                        : 'Importado desde sistema de clubes.',
                ]);

                $imported++;

                return $event;
            });

            if ($event) {
                $successes[] = [
                    'id' => $event->id,
                    'external_id' => $event->external_id,
                    'title' => $event->title,
                    'start_at' => $event->start_at?->toAtomString(),
                    'end_at' => $event->end_at?->toAtomString(),
                    'review_status' => $event->review_status,
                    'final_validation' => $event->final_validation,
                    'publish_to_feed' => (bool) $event->publish_to_feed,
                ];
            }
        }

        $status = $conflicts ? ($imported > 0 ? 'partial' : 'conflicts') : 'ok';

        $responsePayload = [
            'status' => $status,
            'imported' => $imported,
            'skipped' => $skipped,
            'successes' => $successes,
            'conflicts' => $conflicts,
            'overrides' => [],
        ];

        Log::info('Club calendar import response.', [
            'response' => $responsePayload,
        ]);

        return response()->json($responsePayload);
    }

    private function resolveImportUser(int $churchId): ?User
    {
        $user = User::query()
            ->where('church_id', $churchId)
            ->whereIn('role', ['secretary', 'admin'])
            ->orderBy('id')
            ->first();

        if ($user) {
            return $user;
        }

        return User::query()
            ->where('role', 'superadmin')
            ->orderBy('id')
            ->first();
    }

    private function parseUtc(string $value, ?string $timezone): Carbon
    {
        $date = $timezone ? Carbon::parse($value, $timezone) : Carbon::parse($value);
        return $date->utc();
    }

    private function buildErrorConflict(array $payload, string $type, string $message): array
    {
        return [
            'incoming_external_id' => $payload['external_id'] ?? null,
            'incoming_title' => $payload['title'] ?? null,
            'incoming_start_at' => $payload['start_at'] ?? null,
            'incoming_end_at' => $payload['end_at'] ?? null,
            'conflict_type' => $type,
            'message' => $message,
            'conflicts' => [],
        ];
    }

    private function buildOverlapConflict(array $payload, string $type, $conflicts): array
    {
        return [
            'incoming_external_id' => $payload['external_id'],
            'incoming_title' => $payload['title'],
            'incoming_start_at' => $payload['start_at'],
            'incoming_end_at' => $payload['end_at'],
            'conflict_type' => $type,
            'conflicts' => $this->mapConflicts($conflicts),
        ];
    }

    private function mapConflicts($conflicts): array
    {
        return $conflicts->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'start_at' => optional($event->start_at)->toAtomString(),
                'end_at' => optional($event->end_at)->toAtomString(),
                'is_club_event' => (bool) $event->is_club_event,
            ];
        })->values()->all();
    }

    private function flagConflicts($events, User $user, array $payload): void
    {
        $events->each(function (Event $conflictingEvent) use ($user, $payload) {
            if (!$conflictingEvent->requires_club_review) {
                $conflictingEvent->update([
                    'requires_club_review' => true,
                ]);

                EventHistory::create([
                    'event_id' => $conflictingEvent->id,
                    'user_id' => $user->id,
                    'action' => 'club_conflict',
                    'note' => 'Conflicto con evento de club importado.',
                    'changes' => [
                        'requires_club_review' => [
                            'from' => false,
                            'to' => true,
                        ],
                        'club_external_id' => [
                            'from' => null,
                            'to' => $payload['external_id'] ?? null,
                        ],
                    ],
                ]);
            }
        });
    }
}
