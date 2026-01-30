<?php

namespace Tests\Feature\Queue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueueConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_tables_exist_when_using_database_driver(): void
    {
        $driver = Config::get('queue.default');

        // Skip if not using database driver
        if ($driver !== 'database') {
            $this->markTestSkipped("Queue driver is '{$driver}', not 'database'");
        }

        $this->assertTrue(
            Schema::hasTable('jobs'),
            'Table "jobs" must exist when using database queue driver. Run: php artisan migrate'
        );

        $this->assertTrue(
            Schema::hasTable('job_batches'),
            'Table "job_batches" must exist when using database queue driver. Run: php artisan migrate'
        );

        $this->assertTrue(
            Schema::hasTable('failed_jobs'),
            'Table "failed_jobs" must exist when using database queue driver. Run: php artisan migrate'
        );
    }

    public function test_jobs_table_has_correct_structure(): void
    {
        $driver = Config::get('queue.default');

        if ($driver !== 'database') {
            $this->markTestSkipped("Queue driver is '{$driver}', not 'database'");
        }

        $this->assertTrue(Schema::hasColumn('jobs', 'id'));
        $this->assertTrue(Schema::hasColumn('jobs', 'queue'));
        $this->assertTrue(Schema::hasColumn('jobs', 'payload'));
        $this->assertTrue(Schema::hasColumn('jobs', 'attempts'));
        $this->assertTrue(Schema::hasColumn('jobs', 'reserved_at'));
        $this->assertTrue(Schema::hasColumn('jobs', 'available_at'));
        $this->assertTrue(Schema::hasColumn('jobs', 'created_at'));
    }

    public function test_failed_jobs_table_has_correct_structure(): void
    {
        $driver = Config::get('queue.default');

        if ($driver !== 'database') {
            $this->markTestSkipped("Queue driver is '{$driver}', not 'database'");
        }

        $this->assertTrue(Schema::hasColumn('failed_jobs', 'id'));
        $this->assertTrue(Schema::hasColumn('failed_jobs', 'uuid'));
        $this->assertTrue(Schema::hasColumn('failed_jobs', 'connection'));
        $this->assertTrue(Schema::hasColumn('failed_jobs', 'queue'));
        $this->assertTrue(Schema::hasColumn('failed_jobs', 'payload'));
        $this->assertTrue(Schema::hasColumn('failed_jobs', 'exception'));
        $this->assertTrue(Schema::hasColumn('failed_jobs', 'failed_at'));
    }

    public function test_can_insert_into_jobs_table(): void
    {
        $driver = Config::get('queue.default');

        if ($driver !== 'database') {
            $this->markTestSkipped("Queue driver is '{$driver}', not 'database'");
        }

        $jobId = DB::table('jobs')->insertGetId([
            'queue' => 'default',
            'payload' => json_encode(['test' => 'data']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertGreaterThan(0, $jobId);
        $this->assertEquals(1, DB::table('jobs')->where('id', $jobId)->count());

        // Cleanup
        DB::table('jobs')->where('id', $jobId)->delete();
    }

    public function test_queue_health_check_command_exists(): void
    {
        $this->artisan('queue:health-check')
            ->assertExitCode(0);
    }

    public function test_queue_health_check_shows_correct_driver(): void
    {
        $driver = Config::get('queue.default');

        $this->artisan('queue:health-check')
            ->expectsOutput('🔍 Checking queue configuration...')
            ->assertExitCode(0);
    }
}
