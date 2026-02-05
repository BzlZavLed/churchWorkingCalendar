<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingPoint;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingPointController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $request->validate([
            'scope' => ['nullable', 'in:agenda,department'],
        ]);

        $query = $meeting->points()->with(['department', 'reviewer', 'finalizer', 'notes.author']);

        if ($this->canManage($request->user())) {
            return response()->json($query->orderBy('agenda_order')->get());
        }

        if ($request->input('scope') === 'agenda') {
            if (!$meeting->agenda_closed_at && !$meeting->start_at && !$meeting->end_at) {
                return response()->json([]);
            }

            return response()->json(
                $query->orderBy('agenda_order')->get()
            );
        }

        return response()->json(
            $query->where('department_id', $request->user()->department_id)
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('create', MeetingPoint::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user->department_id) {
            throw ValidationException::withMessages([
                'department_id' => ['Department is required.'],
            ]);
        }

        if (!$this->sameChurch($user, $meeting)) {
            abort(403, 'Unauthorized.');
        }

        if ($meeting->agenda_closed_at) {
            throw ValidationException::withMessages([
                'meeting' => ['Agenda is closed.'],
            ]);
        }

        if (now()->greaterThan($meeting->deadlineAt())) {
            throw ValidationException::withMessages([
                'meeting' => ['Submission deadline has passed.'],
            ]);
        }

        $order = (int) $meeting->points()->max('agenda_order');

        $point = MeetingPoint::create([
            'meeting_id' => $meeting->id,
            'department_id' => $user->department_id,
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => MeetingPoint::STATUS_SUBMITTED,
            'agenda_order' => $order + 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json($point->load('department'), 201);
    }

    public function update(Request $request, MeetingPoint $point)
    {
        $this->authorize('update', $point);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $meeting = $point->meeting()->firstOrFail();

        if ($point->status === MeetingPoint::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'status' => ['Accepted points cannot be edited.'],
            ]);
        }

        if ($point->final_status && $point->final_status !== MeetingPoint::FINAL_NEEDS_UPDATE) {
            throw ValidationException::withMessages([
                'status' => ['Finalized points cannot be edited.'],
            ]);
        }

        if ($point->status !== MeetingPoint::STATUS_NEEDS_UPDATE && $point->final_status !== MeetingPoint::FINAL_NEEDS_UPDATE) {
            throw ValidationException::withMessages([
                'status' => ['Only points marked for update can be edited.'],
            ]);
        }

        if ($point->status === MeetingPoint::STATUS_NEEDS_UPDATE && !$this->canResubmitBeforeMeeting($meeting)) {
            throw ValidationException::withMessages([
                'status' => ['Agenda is closed.'],
            ]);
        }

        if ($point->final_status === MeetingPoint::FINAL_NEEDS_UPDATE && !$this->canResubmitAfterMeeting($meeting)) {
            throw ValidationException::withMessages([
                'status' => ['Updates are no longer allowed.'],
            ]);
        }

        $updates = [
            'title' => $data['title'],
            'description' => $data['description'],
            'updated_by' => $request->user()->id,
        ];

        if ($point->status === MeetingPoint::STATUS_NEEDS_UPDATE) {
            $updates['status'] = MeetingPoint::STATUS_SUBMITTED;
            $updates['resubmitted_at'] = now();
        }

        if ($point->final_status === MeetingPoint::FINAL_NEEDS_UPDATE) {
            $updates['resubmitted_at'] = now();
        }

        $point->update($updates);

        return response()->json($point->fresh('department'));
    }

    public function review(Request $request, MeetingPoint $point)
    {
        $this->authorize('review', $point);

        $data = $request->validate([
            'status' => ['required', 'in:accepted,rejected,needs_update'],
            'review_note' => ['nullable', 'string'],
        ]);

        $meeting = $point->meeting()->firstOrFail();

        if ($meeting->agenda_closed_at) {
            throw ValidationException::withMessages([
                'meeting' => ['Agenda is closed.'],
            ]);
        }

        $point->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($point->fresh(['department', 'reviewer', 'notes.author']));
    }

    public function reorder(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'point_ids' => ['required', 'array'],
            'point_ids.*' => ['integer', 'exists:meeting_points,id'],
        ]);

        $ids = $data['point_ids'];

        $points = MeetingPoint::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($points->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'point_ids' => ['All points must belong to the selected meeting.'],
            ]);
        }

        DB::transaction(function () use ($ids, $points) {
            foreach ($ids as $index => $id) {
                $points[$id]->update(['agenda_order' => $index + 1]);
            }
        });

        return response()->json($meeting->points()->orderBy('agenda_order')->get());
    }

    public function activate(Request $request, MeetingPoint $point)
    {
        $this->authorize('manageAgenda', $point);

        $meeting = $point->meeting()->firstOrFail();

        if (!$meeting->start_at || $meeting->end_at) {
            throw ValidationException::withMessages([
                'meeting' => ['Meeting is not live.'],
            ]);
        }

        $meeting->update([
            'active_meeting_point_id' => $point->id,
            'updated_by' => $request->user()->id,
        ]);

        $point->update([
            'active_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($meeting->fresh(['activePoint', 'points.department']));
    }

    public function finalize(Request $request, MeetingPoint $point)
    {
        $this->authorize('manageAgenda', $point);

        $data = $request->validate([
            'final_status' => ['required', 'in:approved,denied,informative,postponed,needs_update'],
            'final_note' => ['nullable', 'string'],
        ]);

        $meeting = $point->meeting()->firstOrFail();

        if (!$meeting->start_at) {
            throw ValidationException::withMessages([
                'meeting' => ['Meeting has not started.'],
            ]);
        }

        $point->update([
            'final_status' => $data['final_status'],
            'final_note' => $data['final_note'] ?? null,
            'finalized_by' => $request->user()->id,
            'finalized_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        if ($meeting->active_meeting_point_id === $point->id) {
            $nextPoint = $meeting->points()
                ->whereNull('final_status')
                ->where('id', '!=', $point->id)
                ->orderBy('agenda_order')
                ->first();

            $meeting->update([
                'active_meeting_point_id' => $nextPoint?->id,
                'updated_by' => $request->user()->id,
            ]);
        }

        return response()->json($point->fresh(['department', 'finalizer', 'notes.author']));
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSecretary();
    }

    private function sameChurch(User $user, Meeting $meeting): bool
    {
        return $user->church_id !== null && $user->church_id === $meeting->church_id;
    }

    private function canResubmitBeforeMeeting(Meeting $meeting): bool
    {
        return $meeting->agenda_closed_at === null;
    }

    private function canResubmitAfterMeeting(Meeting $meeting): bool
    {
        $nextMeetingExists = Meeting::query()
            ->where('church_id', $meeting->church_id)
            ->where('planned_start_at', '>', $meeting->planned_start_at)
            ->exists();

        return !$nextMeetingExists;
    }
}
