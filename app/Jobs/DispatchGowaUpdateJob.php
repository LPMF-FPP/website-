<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Models\GowaUpdateOperation;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchGowaUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $operationId)
    {
        $this->onConnection('gowa-maintenance')->onQueue('gowa-maintenance');
    }

    public function handle(GowaUpdateService $service, GowaUpdateRunner $runner): void
    {
        try {
            $operation = GowaUpdateOperation::query()->find($this->operationId);
            if ($operation === null || $operation->isTerminal()) {
                return;
            }

            if (! $runner->dispatch($operation->id)) {
                $service->fail($operation, 'privileged_runner_unavailable');
            }
        } catch (\Throwable) {
            $operation = GowaUpdateOperation::query()->find($this->operationId);
            if ($operation !== null) {
                $service->fail($operation, 'unexpected_failure');
            }
        }
    }
}
