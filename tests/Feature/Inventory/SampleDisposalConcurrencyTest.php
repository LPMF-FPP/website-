<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\User;
use App\Services\SampleDisposalService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SampleDisposalConcurrencyTest extends TestCase
{
    private array $workers = [];

    private array $payloadFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'pgsql'
            || ! in_array(DB::connection()->getDatabaseName(), ['lpmf_story_1_2_verify', 'lpmf_release_20261013_verify'], true)) {
            throw new \RuntimeException('Concurrency fixtures require an approved isolated verification database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = false;
        $this->seed(PermissionSeeder::class);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'inventory.disposal_retention_days'], ['value' => 90]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach ($this->payloadFiles as $file) {
            @unlink($file);
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_simultaneous_identical_review_submissions_commit_one_batch(): void
    {
        $actor = $this->operator();
        $sample = $this->sample();
        $input = $this->input($actor, [$sample]);
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::create(['item_id' => $item->id, 'location_id' => $location->id, 'on_hand_qty' => 12]);
        $balances = InventoryBalance::all()->toArray();
        $movementCount = InventoryMovement::count();
        DB::beginTransaction();
        User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $first = $this->worker($actor, $input);
        $second = $this->worker($actor, $input);
        $this->waitForBlockedWorkers(2);
        DB::commit();
        $a = $this->workerResult($first);
        $b = $this->workerResult($second);
        $this->assertNotSame($a['pid'], $b['pid']);
        $this->assertSame('complete', $a['status']);
        $this->assertSame('complete', $b['status']);
        $this->assertSame($a['batch_id'], $b['batch_id']);
        $this->assertSame(1, SampleDisposal::count());
        $this->assertSame($a['batch_id'], $sample->fresh()->disposal_id);
        $this->assertSame($balances, InventoryBalance::all()->toArray());
        $this->assertSame($movementCount, InventoryMovement::count());
    }

    public function test_overlapping_batches_reject_the_entire_losing_manifest(): void
    {
        $actorA = $this->operator();
        $actorB = $this->operator();
        $shared = $this->sample();
        $exclusiveA = $this->sample();
        $exclusiveB = $this->sample();
        $a = $this->input($actorA, [$shared, $exclusiveA]);
        $b = $this->input($actorB, [$shared, $exclusiveB]);
        DB::beginTransaction();
        \App\Models\SystemSetting::where('key', 'inventory.disposal_retention_days')->lockForUpdate()->firstOrFail();
        $first = $this->worker($actorA, $a);
        $second = $this->worker($actorB, $b);
        $this->waitForBlockedWorkers(2);
        DB::commit();
        $results = [$this->workerResult($first), $this->workerResult($second)];
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame(['complete', 'stale'], $statuses);
        $this->assertSame(1, SampleDisposal::count());
        $this->assertSame(2, Sample::whereNotNull('disposal_id')->count());
        $loser = $results[0]['status'] === 'stale' ? $exclusiveA : $exclusiveB;
        $this->assertNull($loser->fresh()->disposal_id);
        $this->assertNotNull($shared->fresh()->disposal_id);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_permission_revocation_committing_before_locked_authorization_denies_waiting_execution(): void
    {
        $actor = $this->operator();
        $sample = $this->sample();
        $input = $this->input($actor, [$sample]);
        DB::beginTransaction();
        User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $worker = $this->worker($actor, $input);
        $this->waitForBlockedWorkers(1);
        DB::table('user_permissions')->where('user_id', $actor->id)
            ->where('permission_id', DB::table('permissions')->where('name', 'sample-disposal.execute')->value('id'))
            ->update(['granted' => false]);
        DB::commit();
        $this->assertSame('forbidden', $this->workerResult($worker)['status']);
        $this->assertSame(0, SampleDisposal::count());
        $this->assertNull($sample->fresh()->disposal_id);
    }

    private function waitForBlockedWorkers(int $count): void
    {
        $deadline = microtime(true) + 10;
        do {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("select count(*) as count from pg_stat_activity where application_name = 'lpmf-disposal-race-worker' and wait_event_type = 'Lock'");
            if ((int) $waiting->count >= $count) {
                $this->assertSame($count, (int) $waiting->count);

                return;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        $this->fail('Workers did not overlap while waiting for PostgreSQL locks.');
    }

    private function worker(User $actor, array $input): Process
    {
        $file = tempnam(sys_get_temp_dir(), 'lpmf-disposal-test-');
        chmod($file, 0600);
        file_put_contents($file, json_encode(['actor_id' => $actor->id, 'input' => $input], JSON_THROW_ON_ERROR));
        $this->payloadFiles[] = $file;
        $process = new Process([PHP_BINARY, base_path('tests/Support/disposal-concurrency-worker.php'), $file], base_path(), [
            'APP_ENV' => 'testing', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array',
        ]);
        $process->setTimeout(25);
        $process->start();
        $this->workers[] = $process;

        return $process;
    }

    private function workerResult(Process $worker): array
    {
        $worker->wait();
        $this->assertTrue($worker->isSuccessful(), 'Worker failed: '.$worker->getErrorOutput());

        return json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function operator(): User
    {
        $user = User::factory()->create();
        $user->grantPermission('sample-disposal.view');
        $user->grantPermission('sample-disposal.execute');

        return $user;
    }

    private function sample(): Sample
    {
        $sample = Sample::factory()->create(['disposal_id' => null, 'disposed_at' => null, 'disposal_status' => 'pending']);
        SampleTestProcess::factory()->create(['sample_id' => $sample->id, 'stage' => 'interpretation', 'completed_at' => now()->subDays(91), 'metadata' => ['lhu_number' => 'TEST-LHU-'.$sample->id]]);

        return $sample;
    }

    private function input(User $actor, array $samples): array
    {
        $ids = array_map(fn ($sample) => $sample->id, $samples);
        $preview = app(SampleDisposalService::class)->preview($actor, $ids);

        return ['sample_ids' => $ids, 'review_token' => $preview['review_token'], 'review_confirmed' => '1', 'method' => 'bakar',
            'witnesses' => [['name' => 'Test witness', 'role' => 'Test role']], 'approver_name' => 'Test approver', 'approver_role' => 'Test role'];
    }
}
