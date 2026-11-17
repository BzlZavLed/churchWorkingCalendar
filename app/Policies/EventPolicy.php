<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasCapability('calendar.create');
    }

    public function update(User $user, Event $event): bool
    {
        if ($event->is_conference_event) return false;
        if (($event->status === 'locked' && $event->review_status !== 'changes_requested')
            || $event->status === 'cancelled'
            || ($event->status === 'hold' && (!$event->expires_at || $event->expires_at->lte(now())))) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return ($user->hasCapability('calendar.edit') || ($event->status === 'hold' && $event->created_by === $user->id && $user->hasCapability('calendar.create'))) && $this->inScope($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($event->is_conference_event) return false;
        if ($event->review_status === 'approved') {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasCapability('calendar.edit') && $this->inScope($user, $event);
    }

    public function review(User $user, Event $event): bool
    {
        if ($event->is_conference_event) return false;
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasCapability('calendar.review') && $event->department?->church_id === $user->church_id;
    }

    private function inScope(User $user, Event $event): bool
    {
        return $event->department?->church_id === $user->church_id && ($user->hasCapability('calendar.all_departments') || ($user->department_id !== null && $user->department_id === $event->department_id));
    }

    public function comment(User $user, Event $event): bool
    {
        if ($event->status !== 'locked') return false;
        if ($user->isSuperAdmin()) return true;
        if ($user->hasCapability('calendar.review')) return $this->review($user, $event);
        return $user->department_id !== null && $user->department_id === $event->department_id;
    }
}
