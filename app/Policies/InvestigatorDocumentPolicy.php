<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvestigatorDocumentPolicy
{
    /**
     * Determine whether the user can view documents for an investigator
     */
    public function viewDocuments(User $user, Investigator $investigator): bool
    {
        // Admin and analyst can view all documents
        if (in_array($user->role, ['admin', 'analyst'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the document
     */
    public function view(User $user, Document $document): bool
    {
        // Admin and analyst can view all documents
        if (in_array($user->role, ['admin', 'analyst', 'supervisor'])) {
            return true;
        }

        // Investigators can view documents for their own investigator
        if ($user->role === 'investigator' && $user->investigator_id === $document->investigator_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can upload documents for an investigator
     */
    public function uploadDocument(User $user, Investigator $investigator): bool
    {
        // Admin and analyst can upload documents
        if (in_array($user->role, ['admin', 'analyst'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can download the document
     */
    public function download(User $user, Document $document): bool
    {
        // Admin and analyst can download all documents
        if (in_array($user->role, ['admin', 'analyst', 'supervisor'])) {
            return true;
        }

        // Investigators can download their own documents
        if ($user->role === 'investigator' && $user->investigator_id === $document->investigator_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the document
     */
    public function delete(User $user, Document $document): bool
    {
        // Only admin can delete documents
        if ($user->role === 'admin') {
            return true;
        }

        // Analysts can delete uploaded documents (not generated)
        if ($user->role === 'analyst' && $document->source === 'upload') {
            return true;
        }

        // Investigators can delete their own uploaded documents
        if ($user->role === 'investigator' && 
            $user->investigator_id === $document->investigator_id &&
            $document->source === 'upload') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the document
     */
    public function restore(User $user, Document $document): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the document
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return $user->role === 'admin';
    }
}
