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
}
