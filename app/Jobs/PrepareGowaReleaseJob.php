<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WhatsApp\GowaReleasePreparer;
use App\Support\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class PrepareGowaReleaseJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $version, public readonly int $userId)
    {
        $this->onConnection('gowa-maintenance')->onQueue('gowa-maintenance');
    }

    public function uniqueId(): string
    {
        return 'gowa-release-preparation';
    }

    public function handle(GowaReleasePreparer $preparer): void
    {
        if (! $preparer->dispatch($this->version)) {
            throw new RuntimeException('preparation_dispatch_failed');
        }
    }

    public function failed(?Throwable $exception): void
    {
        ActivityLogger::log('GOWA_PREPARATION_DISPATCH_FAILED', $this->userId, ['type' => 'gowa_release'], null, null, ['version' => $this->version]);
    }
}
