<?php

namespace App\Policies;

use App\Models\Search\Document;

class DocumentPolicy
{
    public function viewAny(?object $user = null): bool
    {
        if (!config('search.enforce_search_policy')) {
            return true;
        }

        return $user?->can('search') ?? false;
    }

    public function view(?object $user, Document $document): bool
    {
        if (!config('search.enforce_search_policy')) {
            return true;
        }

        return $user?->can('search') ?? false;
    }

    public function download(?object $user, Document $document): bool
    {
        if (!$this->view($user, $document)) {
            return false;
        }

        if (!config('search.enforce_download_policy')) {
            return true;
        }

        return $user?->can('documents.download') ?? false;
    }
}
