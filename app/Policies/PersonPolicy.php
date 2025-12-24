<?php

namespace App\Policies;

use App\Models\Person;

class PersonPolicy
{
    public function viewAny(?object $user = null): bool
    {
        if (!config('search.enforce_search_policy')) {
            return true;
        }

        return $user?->can('search') ?? false;
    }

    public function view(?object $user, Person $person): bool
    {
        if (!config('search.enforce_search_policy')) {
            return true;
        }

        return $user?->can('search') ?? false;
    }
}
