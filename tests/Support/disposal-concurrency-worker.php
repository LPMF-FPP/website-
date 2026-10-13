<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SampleDisposalService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'pgsql'
    || ! in_array($connection->getDatabaseName(), ['lpmf_story_1_2_verify', 'lpmf_release_20261013_verify'], true)) {
    throw new RuntimeException('Concurrent worker requires the approved fixture database.');
}
Http::preventStrayRequests();
Mail::fake();
Queue::fake();
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
DB::statement("SET application_name = 'lpmf-disposal-race-worker'");
DB::statement("SET lock_timeout = '15s'");
$pid = DB::selectOne('select pg_backend_pid() as pid')->pid;
try {
    $record = $app->make(SampleDisposalService::class)->execute(User::findOrFail($payload['actor_id']), $payload['input']);
    echo json_encode(['status' => 'complete', 'batch_id' => $record->id, 'pid' => $pid], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Auth\Access\AuthorizationException) {
    echo json_encode(['status' => 'forbidden', 'pid' => $pid], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException) {
    echo json_encode(['status' => 'stale', 'pid' => $pid], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => get_class($exception), 'pid' => $pid], JSON_THROW_ON_ERROR);
    exit(1);
}
