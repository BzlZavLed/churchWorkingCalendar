<?php

namespace App\Support;

use App\Events\DomainUpdated;
use App\Models\Event;
use App\Models\EventNote;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\MeetingPoint;
use App\Models\MeetingPointNote;
use App\Models\User;

class DomainUpdate
{
    public static function forEvent(string $action, Event $event, User|int|null $actor = null): void
    {
        $event->loadMissing('department');
        $actorPayload = self::actorPayload($actor);

        self::dispatch([
            'entity' => 'event',
            'action' => $action,
            ...$actorPayload,
            'church_id' => $event->department?->church_id,
            'department_id' => $event->department_id,
            'event_id' => $event->id,
            'audience_roles' => self::eventAudienceRoles($action, $actorPayload['actor_role'] ?? null),
        ]);
    }

    public static function forEventNote(string $action, EventNote $note, User|int|null $actor = null): void
    {
        $note->loadMissing('event.department');
        $actorPayload = self::actorPayload($actor);

        self::dispatch([
            'entity' => 'event_note',
            'action' => $action,
            ...$actorPayload,
            'church_id' => $note->event?->department?->church_id,
            'department_id' => $note->event?->department_id,
            'event_id' => $note->event_id,
            'note_id' => $note->id,
            'audience_roles' => self::eventNoteAudienceRoles($action, $actorPayload['actor_role'] ?? null),
        ]);
    }

    public static function forMeeting(string $action, Meeting $meeting, User|int|null $actor = null): void
    {
        $actorPayload = self::actorPayload($actor);

        self::dispatch([
            'entity' => 'meeting',
            'action' => $action,
            ...$actorPayload,
            'church_id' => $meeting->church_id,
            'meeting_id' => $meeting->id,
            'audience_roles' => self::meetingAudienceRoles($actorPayload['actor_role'] ?? null),
        ]);
    }

    public static function forMeetingPoint(string $action, MeetingPoint $point, User|int|null $actor = null): void
    {
        $point->loadMissing('meeting');
        $actorPayload = self::actorPayload($actor);

        self::dispatch([
            'entity' => 'meeting_point',
            'action' => $action,
            ...$actorPayload,
            'church_id' => $point->meeting?->church_id,
            'department_id' => $point->department_id,
            'meeting_id' => $point->meeting_id,
            'meeting_point_id' => $point->id,
            'audience_roles' => self::meetingPointAudienceRoles($action, $actorPayload['actor_role'] ?? null),
        ]);
    }

    public static function forMeetingPointNote(string $action, MeetingPointNote $note, User|int|null $actor = null): void
    {
        $note->loadMissing('point.meeting');
        $actorPayload = self::actorPayload($actor);

        self::dispatch([
            'entity' => 'meeting_point_note',
            'action' => $action,
            ...$actorPayload,
            'church_id' => $note->point?->meeting?->church_id,
            'department_id' => $note->point?->department_id,
            'meeting_id' => $note->point?->meeting_id,
            'meeting_point_id' => $note->meeting_point_id,
            'note_id' => $note->id,
            'audience_roles' => self::meetingPointNoteAudienceRoles($actorPayload['actor_role'] ?? null),
        ]);
    }

    public static function forMeetingNote(string $action, MeetingNote $note, User|int|null $actor = null): void
    {
        $note->loadMissing('meeting');
        $actorPayload = self::actorPayload($actor);

        self::dispatch([
            'entity' => 'meeting_note',
            'action' => $action,
            ...$actorPayload,
            'church_id' => $note->meeting?->church_id,
            'meeting_id' => $note->meeting_id,
            'note_id' => $note->id,
            'audience_roles' => self::meetingNoteAudienceRoles($actorPayload['actor_role'] ?? null),
        ]);
    }

    private static function actorPayload(User|int|null $actor): array
    {
        $resolvedActor = match (true) {
            $actor instanceof User => $actor,
            is_int($actor) => User::query()->find($actor),
            default => null,
        };

        return [
            'actor_id' => $resolvedActor?->id,
            'actor_role' => $resolvedActor?->role,
            'actor_name' => $resolvedActor?->name,
        ];
    }

    private static function eventAudienceRoles(string $action, ?string $actorRole): array
    {
        if (in_array($action, ['reviewed', 'published', 'cancelled'], true) && in_array($actorRole, ['secretary', 'superadmin'], true)) {
            return ['admin'];
        }

        return ['secretary', 'superadmin'];
    }

    private static function eventNoteAudienceRoles(string $action, ?string $actorRole): array
    {
        if ($action === 'seen') {
            return ['secretary', 'superadmin'];
        }

        if (in_array($actorRole, ['secretary', 'superadmin'], true)) {
            return ['admin'];
        }

        return ['secretary', 'superadmin'];
    }

    private static function meetingAudienceRoles(?string $actorRole): array
    {
        if (in_array($actorRole, ['secretary', 'superadmin'], true)) {
            return ['admin'];
        }

        return ['secretary', 'superadmin'];
    }

    private static function meetingPointAudienceRoles(string $action, ?string $actorRole): array
    {
        if (in_array($action, ['reviewed', 'activated', 'finalized'], true) && in_array($actorRole, ['secretary', 'superadmin'], true)) {
            return ['admin'];
        }

        return ['secretary', 'superadmin'];
    }

    private static function meetingPointNoteAudienceRoles(?string $actorRole): array
    {
        if (in_array($actorRole, ['secretary', 'superadmin'], true)) {
            return ['admin'];
        }

        return ['secretary', 'superadmin'];
    }

    private static function meetingNoteAudienceRoles(?string $actorRole): array
    {
        if (in_array($actorRole, ['secretary', 'superadmin'], true)) {
            return ['admin'];
        }

        return ['secretary', 'superadmin'];
    }

    private static function dispatch(array $payload): void
    {
        event(new DomainUpdated([
            ...$payload,
            'occurred_at' => now()->toIso8601String(),
        ]));
    }
}
