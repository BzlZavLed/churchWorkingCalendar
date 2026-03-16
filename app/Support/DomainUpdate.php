<?php

namespace App\Support;

use App\Events\DomainUpdated;
use App\Models\Event;
use App\Models\EventNote;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\MeetingPoint;
use App\Models\MeetingPointNote;

class DomainUpdate
{
    public static function forEvent(string $action, Event $event, ?int $actorId = null): void
    {
        $event->loadMissing('department');

        self::dispatch([
            'entity' => 'event',
            'action' => $action,
            'actor_id' => $actorId,
            'church_id' => $event->department?->church_id,
            'department_id' => $event->department_id,
            'event_id' => $event->id,
        ]);
    }

    public static function forEventNote(string $action, EventNote $note, ?int $actorId = null): void
    {
        $note->loadMissing('event.department');

        self::dispatch([
            'entity' => 'event_note',
            'action' => $action,
            'actor_id' => $actorId,
            'church_id' => $note->event?->department?->church_id,
            'department_id' => $note->event?->department_id,
            'event_id' => $note->event_id,
            'note_id' => $note->id,
        ]);
    }

    public static function forMeeting(string $action, Meeting $meeting, ?int $actorId = null): void
    {
        self::dispatch([
            'entity' => 'meeting',
            'action' => $action,
            'actor_id' => $actorId,
            'church_id' => $meeting->church_id,
            'meeting_id' => $meeting->id,
        ]);
    }

    public static function forMeetingPoint(string $action, MeetingPoint $point, ?int $actorId = null): void
    {
        $point->loadMissing('meeting');

        self::dispatch([
            'entity' => 'meeting_point',
            'action' => $action,
            'actor_id' => $actorId,
            'church_id' => $point->meeting?->church_id,
            'department_id' => $point->department_id,
            'meeting_id' => $point->meeting_id,
            'meeting_point_id' => $point->id,
        ]);
    }

    public static function forMeetingPointNote(string $action, MeetingPointNote $note, ?int $actorId = null): void
    {
        $note->loadMissing('point.meeting');

        self::dispatch([
            'entity' => 'meeting_point_note',
            'action' => $action,
            'actor_id' => $actorId,
            'church_id' => $note->point?->meeting?->church_id,
            'department_id' => $note->point?->department_id,
            'meeting_id' => $note->point?->meeting_id,
            'meeting_point_id' => $note->meeting_point_id,
            'note_id' => $note->id,
        ]);
    }

    public static function forMeetingNote(string $action, MeetingNote $note, ?int $actorId = null): void
    {
        $note->loadMissing('meeting');

        self::dispatch([
            'entity' => 'meeting_note',
            'action' => $action,
            'actor_id' => $actorId,
            'church_id' => $note->meeting?->church_id,
            'meeting_id' => $note->meeting_id,
            'note_id' => $note->id,
        ]);
    }

    private static function dispatch(array $payload): void
    {
        event(new DomainUpdated([
            ...$payload,
            'occurred_at' => now()->toIso8601String(),
        ]));
    }
}
