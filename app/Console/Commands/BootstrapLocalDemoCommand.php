<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapLocalDemoCommand extends Command
{
    protected $signature = 'local:bootstrap-demo {--force : Bypass environment confirmation for local/testing only}';

    protected $description = 'Bootstrap admin user and dummy pengujian data for local development';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Command ini hanya boleh dijalankan pada environment local atau testing.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Bootstrap admin local dan dummy pengujian sekarang?')) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->info('== Bootstrap Demo Lokal ==');
        $this->line('Environment: '.app()->environment());
        $this->line('Database: '.config('database.connections.'.config('database.default').'.database'));
        $this->newLine();

        $this->callSilent('db:seed', ['--class' => 'AdminUserSeeder']);
        $this->info('✓ Admin user disiapkan');

        $this->callSilent('db:seed', ['--class' => 'DummyPengujianSeeder']);
        $this->info('✓ Dummy pengujian disiapkan');

        $users = DB::table('users')
            ->whereIn('email', ['admin@example.com', 'test@example.com', 'labmutufarmapol@gmail.com'])
            ->orderBy('id')
            ->get(['id', 'email', 'role']);

        $dummyRequests = DB::table('test_requests')
            ->whereIn('request_number', ['DUMMY-PENGUJIAN-001', 'DUMMY-PENGUJIAN-002'])
            ->orderBy('id')
            ->get(['id', 'request_number', 'receipt_number', 'status']);

        $dummySamples = DB::table('samples')
            ->whereIn('sample_code', ['DUM-SAMP-001', 'DUM-SAMP-002', 'DUM-SAMP-003', 'DUM-SAMP-004', 'DUM-SAMP-005'])
            ->orderBy('id')
            ->get(['id', 'sample_code', 'test_request_id', 'sample_status']);

        $this->newLine();
        $this->info('Admin login lokal:');
        $this->line('  Email    : admin@example.com');
        $this->line('  Password : password');

        $this->newLine();
        $this->table(['ID', 'Email', 'Role'], $users->map(fn ($user) => [(string) $user->id, $user->email, $user->role])->all());

        $this->newLine();
        $this->table(
            ['ID', 'Request Number', 'Receipt Number', 'Status'],
            $dummyRequests->map(fn ($request) => [(string) $request->id, $request->request_number, $request->receipt_number, $request->status])->all()
        );

        $this->newLine();
        $this->table(
            ['ID', 'Sample Code', 'Request ID', 'Status'],
            $dummySamples->map(fn ($sample) => [(string) $sample->id, $sample->sample_code, (string) $sample->test_request_id, $sample->sample_status])->all()
        );

        $this->newLine();
        $this->comment('Jalankan lagi command ini kapan saja jika database lokal ter-reset.');

        return self::SUCCESS;
    }
}
