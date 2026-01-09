<?php

namespace App\Policies;

use App\Models\Objective;
use App\Models\User;

class ObjectivePolicy
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

        return $user->department_id !== null || $user->isAdmin();
    }

    public function update(User $user, Objective $objective): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $objective->department?->church_id !== null
                && $objective->department->church_id === $user->church_id;
        }

        return $user->department_id === $objective->department_id;
    }

    public function delete(User $user, Objective $objective): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $objective->department?->church_id !== null
                && $objective->department->church_id === $user->church_id;
        }

        return $user->department_id === $objective->department_id;
    }
}
