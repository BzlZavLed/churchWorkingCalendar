<?php
namespace App\Http\Controllers;

use App\Models\{Church, Meeting, MeetingPoint};
use App\Services\MeetingWorkflow;
use App\Support\DomainUpdate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingPointController extends Controller
{
    use AuthorizesRequests;
    public function __construct(private MeetingWorkflow $workflow) {}

    public function index(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        $request->validate(['scope' => ['nullable', 'in:agenda,department']]);
        $this->workflow->syncDeadline($meeting);
        $query = $meeting->points()->with(['department', 'notes.author:id,name']);
        if (!$this->workflow->canManage($request->user())) {
            if ($request->input('scope') === 'agenda') {
                if (!$meeting->agenda_closed_at) return response()->json([]);
                $query->where('status', 'accepted');
            } else $query->where('department_id', $request->user()->department_id ?? 0);
        }
        return response()->json($query->orderBy('agenda_order')->get()->map(fn ($p) => $this->workflow->pointFor($p, $request->user())));
    }

    public function report(Request $request)
    {
        $query = MeetingPoint::with(['department', 'meeting', 'continuations:id,related_meeting_point_id,meeting_id']);
        if (!$request->user()->isSuperAdmin()) $query->whereHas('meeting', fn ($q) => $q->where('church_id', $request->user()->church_id ?? 0));
        if (!$this->workflow->canManage($request->user())) $query->where('department_id', $request->user()->department_id ?? 0);
        // Explicit meeting metadata prevents private summaries/attendance leaking through the report relation.
        return response()->json($query->orderByDesc('created_at')->get()->map(function ($p) {
            $data = $p->toArray();
            $data['meeting'] = $p->meeting->only(['id', 'meeting_date', 'planned_start_at', 'end_at', 'location']);
            return $data;
        }));
    }

    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        $this->authorize('create', MeetingPoint::class);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string']]);
        return DB::transaction(function () use ($meeting, $request, $data) {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->workflow->accepting($meeting), 422, 'Submission deadline has passed or agenda is closed.');
            $manager = $this->workflow->canManage($request->user());
            $point = $meeting->points()->create($data + [
                'department_id' => $manager ? null : $request->user()->department_id,
                'status' => $manager ? 'accepted' : 'submitted', 'agenda_order' => (int) $meeting->points()->max('agenda_order') + 1,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
            $point->recordHistory($manager ? 'accepted' : 'submitted', $request->user());
            DomainUpdate::forMeetingPoint('created', $point, $request->user());
            return response()->json($point->load('department'), 201);
        });
    }

    public function update(Request $request, MeetingPoint $point)
    {
        $this->authorize('update', $point);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string']]);
        return DB::transaction(function () use ($point, $request, $data) {
            Church::whereKey($point->meeting->church_id)->lockForUpdate()->firstOrFail();
            $meeting = Meeting::whereKey($point->meeting_id)->lockForUpdate()->firstOrFail();
            $point = MeetingPoint::whereKey($point->id)->lockForUpdate()->firstOrFail();
            if ($point->final_status === 'needs_update' && $meeting->end_at) {
                abort_if($point->continuations()->exists(), 422, 'Edit the follow-up point in the next meeting.');
                // Keep the closed meeting's proposal and vote immutable; save the next submission separately.
                $point->update(['revision' => $data, 'resubmitted_at' => now(), 'updated_by' => $request->user()->id]);
            } else {
                abort_unless($this->workflow->accepting($meeting), 422, 'Agenda is closed.');
                abort_unless($point->status === 'needs_update' && !$point->final_status, 422, 'Only requests for updates can be resubmitted.');
                $point->update($data + ['status' => 'submitted', 'resubmitted_at' => now(), 'updated_by' => $request->user()->id]);
            }
            $point->recordHistory('resubmitted', $request->user());
            DomainUpdate::forMeetingPoint('updated', $point, $request->user());
            return response()->json($point->load('department'));
        });
    }

    public function review(Request $request, MeetingPoint $point)
    {
        $this->authorize('review', $point);
        $data = $request->validate(['status' => ['required', 'in:accepted,rejected,needs_update'], 'review_note' => ['required_if:status,needs_update', 'nullable', 'string']]);
        return DB::transaction(function () use ($point, $request, $data) {
            $meeting = Meeting::whereKey($point->meeting_id)->lockForUpdate()->firstOrFail();
            abort_unless($this->workflow->accepting($meeting), 422, 'Agenda is closed.');
            $point = MeetingPoint::whereKey($point->id)->lockForUpdate()->firstOrFail();
            $point->update(['status' => $data['status'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_by' => $request->user()->id]);
            $point->recordHistory($data['status'], $request->user(), $data['review_note'] ?? null);
            DomainUpdate::forMeetingPoint('reviewed', $point, $request->user());
            return response()->json($point->load('department'));
        });
    }

    public function reorder(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        $data = $request->validate(['point_ids' => ['required', 'array'], 'point_ids.*' => ['integer', 'distinct']]);
        return DB::transaction(function () use ($meeting, $data, $request) {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->workflow->accepting($meeting), 422, 'Agenda order is locked.');
            $ids = $data['point_ids'];
            abort_unless($meeting->points()->count() === count($ids) && $meeting->points()->whereIn('id', $ids)->count() === count($ids), 422, 'Provide every point of this meeting exactly once.');
            foreach ($ids as $order => $id) $meeting->points()->whereKey($id)->update(['agenda_order' => $order + 1]);
            DomainUpdate::forMeeting('reordered', $meeting, $request->user());
            return response()->json($meeting->points()->orderBy('agenda_order')->get());
        });
    }

    public function activate(Request $request, MeetingPoint $point)
    {
        $this->authorize('manageAgenda', $point);
        return DB::transaction(function () use ($point, $request) {
            $meeting = Meeting::whereKey($point->meeting_id)->lockForUpdate()->firstOrFail();
            $point = MeetingPoint::whereKey($point->id)->lockForUpdate()->firstOrFail();
            abort_unless($meeting->start_at && !$meeting->end_at && $point->status === 'accepted' && !$point->final_status, 422, 'Only an undecided accepted point in a live meeting can be activated.');
            abort_if($meeting->active_meeting_point_id && $meeting->active_meeting_point_id !== $point->id, 422, 'Record the active point decision first.');
            if ($meeting->active_meeting_point_id !== $point->id) {
                $meeting->update(['active_meeting_point_id' => $point->id]);
                $point->update(['active_at' => now()]);
                $point->recordHistory('active', $request->user());
            }
            DomainUpdate::forMeetingPoint('activated', $point, $request->user());
            return response()->json($this->workflow->meetingFor($meeting, $request->user()));
        });
    }

    public function finalize(Request $request, MeetingPoint $point)
    {
        $this->authorize('manageAgenda', $point);
        $data = $request->validate(['final_status' => ['required', 'in:approved,denied,informative,postponed,needs_update'], 'final_note' => ['required_if:final_status,needs_update', 'nullable', 'string']]);
        return DB::transaction(function () use ($point, $request, $data) {
            $meeting = Meeting::whereKey($point->meeting_id)->lockForUpdate()->firstOrFail();
            $point = MeetingPoint::whereKey($point->id)->lockForUpdate()->firstOrFail();
            abort_unless($meeting->start_at && !$meeting->end_at && $point->status === 'accepted' && !$point->final_status && $meeting->active_meeting_point_id === $point->id, 422, 'Activate an undecided accepted point during the live meeting first.');
            $point->update(['final_status' => $data['final_status'], 'final_note' => $data['final_note'] ?? null, 'finalized_by' => $request->user()->id, 'finalized_at' => now(), 'updated_by' => $request->user()->id]);
            $point->recordHistory($data['final_status'], $request->user(), $data['final_note'] ?? null);
            $meeting->update(['active_meeting_point_id' => null]);
            DomainUpdate::forMeetingPoint('finalized', $point, $request->user());
            return response()->json($point->load('department'));
        });
    }
}
