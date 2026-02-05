<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->church_id !== null && $user->church_id === $meeting->church_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSecretary();
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isSecretary() && $user->church_id === $meeting->church_id;
    }
}
