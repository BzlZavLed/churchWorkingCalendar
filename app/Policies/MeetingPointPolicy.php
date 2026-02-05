<?php

namespace App\Policies;

use App\Models\MeetingPoint;
use App\Models\User;

class MeetingPointPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->department_id !== null;
    }

    public function update(User $user, MeetingPoint $point): bool
    {
        return $user->isAdmin() && $user->department_id === $point->department_id;
    }

    public function review(User $user, MeetingPoint $point): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isSecretary() && $user->church_id === $point->meeting?->church_id;
    }

    public function manageAgenda(User $user, MeetingPoint $point): bool
    {
        return $this->review($user, $point);
    }
}
