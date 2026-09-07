<?php

namespace App\Policies;

use App\Models\ExpertWitnessRequest;
use App\Models\User;

class ExpertWitnessRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sahli.view');
    }

    public function view(User $user, ExpertWitnessRequest $request): bool
    {
        return $user->hasPermission('sahli.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sahli.create');
    }

    public function update(User $user, ExpertWitnessRequest $request): bool
    {
        return $user->hasPermission('sahli.edit');
    }
}
