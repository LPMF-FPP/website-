<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

trait RecreatesApprovedBrowserDatabase
{
    public function recreateApprovedBrowserDatabase(): void
    {
        $connection = $this->app->make('db')->connection();
        if (! $this->app->environment('testing') || $connection->getDriverName() !== 'pgsql'
            || ! in_array($connection->getDatabaseName(), ['lis_db_test', 'lpmf_story_1_2_verify', 'lpmf_release_20261013_verify'], true)) {
            throw new RuntimeException('Browser fixture recreation requires an explicitly approved PostgreSQL test database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->app[Kernel::class]->setArtisan(null);
    }
}
