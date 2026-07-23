<?php

namespace App\Policies;

use App\Models\GuestVisit;
use App\Models\User;

class GuestVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout']);
    }

    public function view(User $user, GuestVisit $guestVisit): bool
    {
        return $user->hasPermission('guest-book.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('guest-book.create');
    }

    public function update(User $user, GuestVisit $guestVisit): bool
    {
        return $user->hasPermission('guest-book.edit');
    }

    public function delete(User $user, GuestVisit $guestVisit): bool
    {
        return $user->hasPermission('guest-book.delete');
    }
}
