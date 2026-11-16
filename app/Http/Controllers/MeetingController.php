<?php
namespace App\Http\Controllers;

use App\Models\{Church, Meeting, User};
use App\Services\MeetingWorkflow;
use App\Support\DomainUpdate;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    use AuthorizesRequests;
    public function __construct(private MeetingWorkflow $workflow) {}

    public function index(Request $request)
    {
        $request->validate(['church_id' => ['nullable', 'exists:churches,id']]);
        $query = Meeting::query()->orderByDesc('planned_start_at');
        if (!$request->user()->isSuperAdmin()) $query->where('church_id', $request->user()->church_id ?? 0);
        elseif ($request->filled('church_id')) $query->where('church_id', $request->integer('church_id'));
        return response()->json($query->get()->map(fn ($m) => $this->workflow->meetingFor($m, $request->user())));
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        return response()->json($this->workflow->meetingFor($meeting, $request->user()));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Meeting::class);
        $data = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'], 'meeting_date' => ['required', 'date'],
            'planned_start_at' => ['required', 'date', 'after:now'], 'location' => ['nullable', 'string', 'max:255'],
        ]);
        $churchId = $request->user()->isSuperAdmin() ? ($data['church_id'] ?? null) : $request->user()->church_id;
        abort_unless($churchId, 422, 'Church is required.');
        $start = Carbon::parse($data['planned_start_at']);
        abort_unless($start->toDateString() === Carbon::parse($data['meeting_date'])->toDateString(), 422, 'Meeting date must match planned start date.');
        $meeting = DB::transaction(function () use ($churchId, $data, $start, $request) {
            Church::whereKey($churchId)->lockForUpdate()->firstOrFail();
            $meeting = Meeting::create(['church_id' => $churchId, 'meeting_date' => $data['meeting_date'], 'planned_start_at' => $start->utc(), 'location' => $data['location'] ?? null, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $this->workflow->carryover($meeting, $request->user());
            return $meeting;
        });
        $this->workflow->syncDeadline($meeting);
        DomainUpdate::forMeeting('created', $meeting, $request->user());
        return response()->json($this->workflow->meetingFor($meeting, $request->user()), 201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        $data = $request->validate([
            'meeting_date' => ['sometimes', 'date'], 'planned_start_at' => ['sometimes', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'summary_text' => ['sometimes', 'nullable', 'string'], 'summary_public' => ['sometimes', 'boolean'],
            'opening_prayer' => ['sometimes', 'nullable', 'string'], 'opening_remarks' => ['sometimes', 'nullable', 'string'],
            'attendance' => ['sometimes', 'array', 'max:500'],
            'attendance.*.name' => ['required', 'string', 'max:255'],
            'attendance.*.status' => ['required', 'in:present,absent,online'],
        ]);
        return DB::transaction(function () use ($meeting, $data, $request) {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->workflow->syncDeadline($meeting);
            if (array_key_exists('summary_public', $data) || array_key_exists('summary_text', $data)) abort_unless($meeting->end_at, 422, 'Finish the meeting before publishing its summary.');
            if ($meeting->end_at) abort_if(count(array_diff(array_keys($data), ['summary_text', 'summary_public'])) > 0, 422, 'Closed meeting records cannot be edited.');
            if (isset($data['meeting_date']) || isset($data['planned_start_at'])) {
                abort_unless($this->workflow->accepting($meeting), 422, 'The schedule is locked.');
                $start = Carbon::parse($data['planned_start_at'] ?? $meeting->planned_start_at);
                $date = Carbon::parse($data['meeting_date'] ?? $meeting->meeting_date)->toDateString();
                abort_unless($start->toDateString() === $date && $start->isFuture(), 422, 'Invalid meeting date.');
                $data['planned_start_at'] = $start->utc();
                $data['meeting_date'] = $date;
            }
            if (isset($data['attendance'])) $data['attendance'] = array_map(fn ($row) => ['name' => $row['name'], 'status' => $row['status']], $data['attendance']);
            $meeting->update($data + ['updated_by' => $request->user()->id]);
            DomainUpdate::forMeeting('updated', $meeting, $request->user());
            return response()->json($this->workflow->meetingFor($meeting, $request->user()));
        });
    }

    public function closeAgenda(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        abort_if(now()->lessThan($meeting->deadlineAt()), 422, 'Agenda closes automatically 24 hours before the meeting.');
        $this->workflow->syncDeadline($meeting);
        return response()->json($this->workflow->meetingFor($meeting, $request->user()));
    }

    public function start(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        return DB::transaction(function () use ($meeting, $request) {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            abort_if($meeting->end_at, 422, 'Meeting has ended.');
            abort_if(now()->lessThan($meeting->planned_start_at), 422, 'The scheduled meeting time has not arrived.');
            $this->workflow->syncDeadline($meeting);
            if (!$meeting->start_at) $meeting->update(['start_at' => now(), 'opened_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            DomainUpdate::forMeeting('started', $meeting, $request->user());
            return response()->json($this->workflow->meetingFor($meeting, $request->user()));
        });
    }

    public function adjourn(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        return DB::transaction(function () use ($meeting, $request) {
            Church::whereKey($meeting->church_id)->lockForUpdate()->firstOrFail();
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            abort_unless($meeting->start_at, 422, 'Meeting has not started.');
            if (!$meeting->end_at) {
                abort_if($meeting->points()->where('status', 'accepted')->whereNull('final_status')->exists(), 422, 'Record a decision for every accepted agenda point before closing.');
                $labels = $request->input('locale') === 'en'
                    ? ['approved' => 'Accepted', 'denied' => 'Rejected', 'needs_update' => 'Updates requested', 'postponed' => 'Next meeting', 'informative' => 'Informative']
                    : ['approved' => 'Aceptado', 'denied' => 'Rechazado', 'needs_update' => 'Actualización solicitada', 'postponed' => 'Próxima junta', 'informative' => 'Informativo'];
                $summary = $meeting->points()->where('status', 'accepted')->orderBy('agenda_order')->get()->map(fn ($p) => $p->title.' ('.($labels[$p->final_status] ?? $p->final_status).')'.($p->final_note ? ': '.$p->final_note : ''))->implode("\n");
                $meeting->update(['end_at' => now(), 'active_meeting_point_id' => null, 'summary_generated' => $summary, 'summary_public' => false, 'updated_by' => $request->user()->id]);
                $next = Meeting::where('church_id', $meeting->church_id)->where('planned_start_at', '>', $meeting->planned_start_at)->whereNull('agenda_closed_at')->whereNull('start_at')->whereNull('end_at')->orderBy('planned_start_at')->first();
                if ($next) $this->workflow->carryover($next, $request->user());
            }
            DomainUpdate::forMeeting('adjourned', $meeting, $request->user());
            return response()->json($this->workflow->meetingFor($meeting, $request->user()));
        });
    }
}
