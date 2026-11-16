<?php

namespace App\Policies;

use App\Models\Objective;
use App\Models\User;

class ObjectivePolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Objective $objective): bool
    {
        if ($user->isSuperAdmin()) return true;
        if ($objective->department?->church_id !== $user->church_id) return false;
        return $user->isSecretary() || $user->department_id === $objective->department_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_MEMBER], true) && $user->department_id !== null);
    }

    public function update(User $user, Objective $objective): bool
    {
        return $user->isSuperAdmin() || ($this->create($user) && $this->view($user, $objective));
    }

    public function delete(User $user, Objective $objective): bool
    {
        return $this->update($user, $objective);
    }
}
