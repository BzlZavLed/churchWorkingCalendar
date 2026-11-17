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

        $churchId = $user->church_id ?? $user->department?->church_id;

        return $churchId !== null && $churchId === $meeting->church_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasCapability('meetings.manage');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $churchId = $user->church_id ?? $user->department?->church_id;

        return $user->hasCapability('meetings.manage') && $churchId === $meeting->church_id;
    }
}
