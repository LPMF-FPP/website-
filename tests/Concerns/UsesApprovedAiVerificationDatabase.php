<?php

declare(strict_types=1);

namespace Tests\Concerns;

use RuntimeException;

trait UsesApprovedAiVerificationDatabase
{
    protected function setUpTraits(): array
    {
        $connection = $this->app->make('db')->connection();
        if (! $this->app->environment('testing') || $connection->getDriverName() !== 'pgsql'
            || $connection->getDatabaseName() !== 'lpmf_release_20261013_verify') {
            throw new RuntimeException('AI verification requires the approved isolated PostgreSQL database before any fixture migration.');
        }

        return parent::setUpTraits();
    }
}
