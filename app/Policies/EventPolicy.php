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
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isSecretary()) {
            return false;
        }

        return $user->department_id !== null || $user->isAdmin();
    }

    public function update(User $user, Event $event): bool
    {
        if (($event->status === 'locked' && $event->review_status !== 'changes_requested')
            || $event->status === 'cancelled'
            || ($event->status === 'hold' && (!$event->expires_at || $event->expires_at->lte(now())))) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isSecretary()) {
            return false;
        }

        if ($user->isAdmin()) {
            return $event->department?->church_id !== null
                && $event->department->church_id === $user->church_id;
        }

        return $user->department_id === $event->department_id;
    }

    public function delete(User $user, Event $event): bool
    {
        if ($event->review_status === 'approved') {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isSecretary()) {
            return false;
        }

        if ($user->isAdmin()) {
            return $event->department?->church_id !== null
                && $event->department->church_id === $user->church_id;
        }

        return $user->department_id === $event->department_id;
    }

    public function review(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isSecretary()) {
            return $event->department?->church_id !== null
                && $event->department->church_id === $user->church_id;
        }

        return false;
    }

    public function comment(User $user, Event $event): bool
    {
        if ($event->status !== 'locked') return false;
        if ($user->isSuperAdmin()) return true;
        if ($user->isSecretary()) return $this->review($user, $event);
        return $user->department_id !== null && $user->department_id === $event->department_id;
    }
}
