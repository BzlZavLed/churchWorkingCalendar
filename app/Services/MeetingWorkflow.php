<?php
namespace App\Services;

use App\Models\{Meeting, MeetingPoint, User};
use App\Support\DomainUpdate;

class MeetingWorkflow
{
    public function canManage(User $user): bool { return $user->hasCapability('meetings.manage') || $user->isSuperAdmin(); }

    public function syncDeadline(Meeting $meeting): void
    {
        if (!$meeting->agenda_closed_at && now()->greaterThanOrEqualTo($meeting->deadlineAt())) {
            if (Meeting::whereKey($meeting->id)->whereNull('agenda_closed_at')->update(['agenda_closed_at' => $meeting->deadlineAt()])) {
                $meeting->refresh();
                DomainUpdate::forMeeting('agenda_closed', $meeting);
            } else { $meeting->refresh(); }
        }
    }

    public function accepting(Meeting $meeting): bool
    {
        return !$meeting->agenda_closed_at && !$meeting->start_at && !$meeting->end_at && now()->lessThan($meeting->deadlineAt());
    }

    public function pointFor(MeetingPoint $point, User $user, bool $agenda = false): array
    {
        $point->loadMissing(['department', 'meeting']);
        if ($this->canManage($user) || ($user->department_id !== null && $point->department_id === $user->department_id)) {
            $data = $point->toArray();
            unset($data['meeting']);
            return $data;
        }
        // Shared agenda is intentionally a proposal list, never another department's live vote/history.
        return $point->only(['id', 'meeting_id', 'department_id', 'related_meeting_point_id', 'title', 'description', 'agenda_order'])
            + ['department' => $point->department?->only(['id', 'name', 'color']), 'status' => 'accepted'];
    }

    public function meetingFor(Meeting $meeting, User $user): array
    {
        $this->syncDeadline($meeting);
        $meeting->loadMissing(['opener:id,name', 'meetingNotes.author:id,name']);
        if ($this->canManage($user)) return array_replace($meeting->toArray(), $meeting->end_at ? $this->minutesFor($meeting) : []) + ['submission_deadline' => $meeting->deadlineAt()->toIso8601String()];
        $data = $meeting->only(['id', 'church_id', 'meeting_date', 'planned_start_at', 'start_at', 'end_at', 'location', 'agenda_closed_at', 'summary_public', 'minutes_number']);
        $data['submission_deadline'] = $meeting->deadlineAt()->toIso8601String();
        if ($meeting->end_at && $meeting->summary_public) {
            $data += $this->minutesFor($meeting);
            $data += $meeting->only(['opening_prayer', 'opening_remarks', 'opening_prayer_recorded_at', 'opening_remarks_recorded_at', 'closing_notes', 'attendance']);
        }
        return $data;
    }

    // Explicit allowlist keeps working notes and legacy free-text summaries out of minutes.
    public function minutesFor(Meeting $meeting): array
    {
        $outcomes = $meeting->points()->where('status', 'accepted')->whereNotNull('final_status')->orderBy('agenda_order')->orderBy('id')->get(['id','title','final_status','finalized_at']);
        $reviews = $meeting->points()->where('status','accepted')->where('calendar_review',true)->orderBy('agenda_order')->get(['id','title','calendar_decisions'])->map(function ($point) {
            return ['id'=>$point->id,'title'=>$point->title,'calendar_decisions'=>array_map(fn($decision)=>array_intersect_key($decision,array_flip(['title','department','start_at','status','at','actor'])), $point->calendar_decisions ?? [])];
        });
        return ['outcomes'=>$outcomes,'calendar_reviews'=>$reviews,'summary_text'=>null,'summary_generated'=>null];
    }

    // Caller locks the church row, serializing creation/adjournment and preventing duplicate carryovers.
    public function carryover(Meeting $target, User $actor): void
    {
        if (!$this->accepting($target)) return;
        $points = MeetingPoint::whereHas('meeting', fn ($q) => $q->where('church_id', $target->church_id)->whereNotNull('end_at')->where('planned_start_at', '<', $target->planned_start_at))
            ->whereIn('final_status', ['postponed', 'needs_update'])->whereDoesntHave('continuations')->orderBy('id')->get();
        $order = (int) $target->points()->max('agenda_order');
        foreach ($points as $source) {
            $copy = MeetingPoint::create([
                'meeting_id' => $target->id, 'department_id' => $source->department_id,
                'calendar_review' => $source->calendar_review, 'calendar_from' => $source->calendar_from, 'calendar_until' => $source->calendar_until,
                'related_meeting_point_id' => $source->id, 'title' => $source->revision['title'] ?? $source->title, 'description' => $source->revision['description'] ?? $source->description,
                'status' => ($source->final_status === 'postponed' || $source->revision) ? 'submitted' : 'needs_update',
                'review_note' => $source->final_status === 'needs_update' ? $source->final_note : null,
                'agenda_order' => ++$order, 'created_by' => $source->created_by, 'updated_by' => $actor->id,
            ]);
            $copy->recordHistory('carried_forward', $actor, 'Previous point #'.$source->id);
        }
    }
}
