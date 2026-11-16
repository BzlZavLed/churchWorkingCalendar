<?php
namespace App\Services;

use App\Models\{Meeting, MeetingPoint, User};
use App\Support\DomainUpdate;

class MeetingWorkflow
{
    public function canManage(User $user): bool { return $user->isSecretary() || $user->isSuperAdmin(); }

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
        if ($this->canManage($user)) return $meeting->toArray() + ['submission_deadline' => $meeting->deadlineAt()->toIso8601String()];
        $data = $meeting->only(['id', 'church_id', 'meeting_date', 'planned_start_at', 'start_at', 'end_at', 'location', 'agenda_closed_at', 'summary_public']);
        $data['submission_deadline'] = $meeting->deadlineAt()->toIso8601String();
        if ($meeting->end_at && $meeting->summary_public) {
            $data += $meeting->only(['summary_text', 'summary_generated', 'opening_prayer', 'opening_remarks', 'attendance']);
        }
        return $data;
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
                'related_meeting_point_id' => $source->id, 'title' => $source->revision['title'] ?? $source->title, 'description' => $source->revision['description'] ?? $source->description,
                'status' => ($source->final_status === 'postponed' || $source->revision) ? 'submitted' : 'needs_update',
                'review_note' => $source->final_status === 'needs_update' ? $source->final_note : null,
                'agenda_order' => ++$order, 'created_by' => $source->created_by, 'updated_by' => $actor->id,
            ]);
            $copy->recordHistory('carried_forward', $actor, 'Previous point #'.$source->id);
        }
    }
}
