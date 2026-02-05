<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
        ]);

        $query = Meeting::query()
            ->with(['activePoint', 'points.department', 'opener', 'meetingNotes.author'])
            ->orderByDesc('planned_start_at');

        if (!$request->user()->isSuperAdmin()) {
            $query->where('church_id', $request->user()->church_id);
        } elseif ($request->filled('church_id')) {
            $query->where('church_id', $request->integer('church_id'));
        }

        $meetings = $query->get();

        return response()->json($meetings);
    }

    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $meeting->load(['activePoint', 'points.department', 'opener', 'meetingNotes.author']);

        return response()->json($meeting);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Meeting::class);

        $data = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'meeting_date' => ['required', 'date'],
            'planned_start_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $meetingDate = Carbon::parse($data['meeting_date'])->toDateString();
        $plannedStart = Carbon::parse($data['planned_start_at']);

        if ($plannedStart->toDateString() !== $meetingDate) {
            throw ValidationException::withMessages([
                'meeting_date' => ['Meeting date must match the planned start date.'],
            ]);
        }

        $churchId = $this->resolveChurchId($request, $data['church_id'] ?? null);

        $meeting = DB::transaction(function () use ($request, $meetingDate, $plannedStart, $churchId, $data) {
            $meeting = Meeting::create([
                'church_id' => $churchId,
                'meeting_date' => $meetingDate,
                'planned_start_at' => $plannedStart->utc(),
                'location' => $data['location'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->cloneCarryoverPoints($meeting, $request->user()->id);

            return $meeting;
        });

        return response()->json($meeting->load(['points.department']), 201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'meeting_date' => ['sometimes', 'date'],
            'planned_start_at' => ['sometimes', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'summary_text' => ['nullable', 'string'],
            'summary_public' => ['nullable', 'boolean'],
            'opening_prayer' => ['nullable', 'string'],
            'opening_remarks' => ['nullable', 'string'],
        ]);

        $meetingDate = array_key_exists('meeting_date', $data)
            ? Carbon::parse($data['meeting_date'])->toDateString()
            : $meeting->meeting_date->toDateString();

        $plannedStart = array_key_exists('planned_start_at', $data)
            ? Carbon::parse($data['planned_start_at'])
            : $meeting->planned_start_at;

        if ($plannedStart->toDateString() !== $meetingDate) {
            throw ValidationException::withMessages([
                'meeting_date' => ['Meeting date must match the planned start date.'],
            ]);
        }

        $meeting->update([
            'meeting_date' => $meetingDate,
            'planned_start_at' => $plannedStart->utc(),
            'location' => array_key_exists('location', $data) ? $data['location'] : $meeting->location,
            'summary_text' => array_key_exists('summary_text', $data) ? $data['summary_text'] : $meeting->summary_text,
            'summary_public' => array_key_exists('summary_public', $data)
                ? (bool) $data['summary_public']
                : $meeting->summary_public,
            'opening_prayer' => array_key_exists('opening_prayer', $data)
                ? $data['opening_prayer']
                : $meeting->opening_prayer,
            'opening_remarks' => array_key_exists('opening_remarks', $data)
                ? $data['opening_remarks']
                : $meeting->opening_remarks,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($meeting->fresh(['activePoint', 'points.department']));
    }

    public function closeAgenda(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        if ($meeting->agenda_closed_at) {
            return response()->json($meeting);
        }

        $meeting->update([
            'agenda_closed_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($meeting);
    }

    public function start(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        if ($meeting->start_at) {
            return response()->json($meeting);
        }

        $meeting->update([
            'start_at' => now(),
            'opened_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($meeting);
    }

    public function adjourn(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $meeting = DB::transaction(function () use ($request, $meeting) {
            if (!$meeting->end_at) {
                $meeting->update([
                    'end_at' => now(),
                    'active_meeting_point_id' => null,
                    'updated_by' => $request->user()->id,
                ]);
            }

            $meeting->points()
                ->whereNull('final_status')
                ->update([
                    'final_status' => MeetingPoint::FINAL_POSTPONED,
                    'finalized_by' => $request->user()->id,
                    'finalized_at' => now(),
                ]);

            $meeting->update([
                'summary_generated' => $this->generateSummary($meeting->fresh('points.department')),
                'updated_by' => $request->user()->id,
            ]);

            return $meeting;
        });

        return response()->json($meeting->fresh(['points.department']));
    }

    private function resolveChurchId(Request $request, ?int $requestedChurchId): int
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            if (!$requestedChurchId) {
                throw ValidationException::withMessages([
                    'church_id' => ['Church is required.'],
                ]);
            }

            return $requestedChurchId;
        }

        if (!$user->church_id) {
            throw ValidationException::withMessages([
                'church_id' => ['Church is required.'],
            ]);
        }

        return $user->church_id;
    }

    private function cloneCarryoverPoints(Meeting $meeting, int $userId): void
    {
        $previousMeeting = Meeting::query()
            ->where('church_id', $meeting->church_id)
            ->where('id', '!=', $meeting->id)
            ->whereNotNull('end_at')
            ->orderByDesc('planned_start_at')
            ->first();

        if (!$previousMeeting) {
            return;
        }

        $carryover = $previousMeeting->points()
            ->whereIn('final_status', [MeetingPoint::FINAL_POSTPONED, MeetingPoint::FINAL_NEEDS_UPDATE])
            ->orderBy('agenda_order')
            ->get();

        if ($carryover->isEmpty()) {
            return;
        }

        $order = 1;
        foreach ($carryover as $point) {
            $status = $point->final_status === MeetingPoint::FINAL_POSTPONED
                ? MeetingPoint::STATUS_ACCEPTED
                : MeetingPoint::STATUS_SUBMITTED;

            MeetingPoint::create([
                'meeting_id' => $meeting->id,
                'department_id' => $point->department_id,
                'related_meeting_point_id' => $point->id,
                'title' => $point->title,
                'description' => $point->description,
                'status' => $status,
                'agenda_order' => $order++,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function generateSummary(Meeting $meeting): string
    {
        $lines = [];
        foreach ($meeting->points as $point) {
            $status = $point->final_status ?? $point->status;
            $note = $point->final_note ? " - {$point->final_note}" : '';
            $lines[] = "{$point->title} ({$status}){$note}";
        }

        return implode("\n", $lines);
    }
}
