<?php

namespace App\Policies;

use App\Models\Investigator;
use App\Models\User;

class InvestigatorPolicy
{
    /**
     * Determine if the user can view any investigators.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can view the investigator.
     */
    public function view(User $user, Investigator $investigator): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can update the investigator.
     */
    public function update(User $user, Investigator $investigator): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can delete the investigator.
     */
    public function delete(User $user, Investigator $investigator): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can view documents for the investigator.
     */
    public function viewDocuments(User $user, Investigator $investigator): bool
    {
        // Admin can view all documents
        if ($user->role === 'admin') {
            return true;
        }

        // Users can view documents for their own investigator
        if ($user->investigator_id === $investigator->id) {
            return true;
        }

        // Analysts and supervisors can view all documents
        if (in_array($user->role, ['analis', 'penyelia', 'manajer_teknis', 'supervisor'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create documents for the investigator.
     */
    public function createDocuments(User $user, Investigator $investigator): bool
    {
        // Same logic as viewDocuments
        return $this->viewDocuments($user, $investigator);
    }

    /**
     * Determine if the user can delete documents for the investigator.
     */
    public function deleteDocuments(User $user, Investigator $investigator): bool
    {
        // Admin can delete all
        if ($user->role === 'admin') {
            return true;
        }

        // Users can delete their own investigator's documents
        if ($user->investigator_id === $investigator->id) {
            return true;
        }

        return false;
    }
}
