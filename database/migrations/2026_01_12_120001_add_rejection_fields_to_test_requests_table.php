<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateStatusEnum([
            'submitted',
            'verified',
            'received',
            'in_testing',
            'analysis',
            'quality_check',
            'ready_for_delivery',
            'completed',
            'rejected',
        ]);

        Schema::table('test_requests', function (Blueprint $table) {
            $table->text('rejected_reason')->nullable()->after('completed_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_reason');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_reason', 'rejected_at']);
        });

        $this->updateStatusEnum([
            'submitted',
            'verified',
            'received',
            'in_testing',
            'analysis',
            'quality_check',
            'ready_for_delivery',
            'completed',
        ]);
    }

    private function updateStatusEnum(array $statuses): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $values = implode("','", $statuses);
            DB::statement("ALTER TABLE test_requests MODIFY status ENUM('{$values}') DEFAULT 'submitted'");
            return;
        }

        if ($driver === 'pgsql') {
            $typeExists = DB::table('pg_type')
                ->where('typname', 'test_requests_status')
                ->exists();

            if ($typeExists) {
                $hasRejected = DB::table('pg_type')
                    ->join('pg_enum', 'pg_enum.enumtypid', '=', 'pg_type.oid')
                    ->where('pg_type.typname', 'test_requests_status')
                    ->where('pg_enum.enumlabel', 'rejected')
                    ->exists();

                if (!$hasRejected && in_array('rejected', $statuses, true)) {
                    DB::statement("ALTER TYPE test_requests_status ADD VALUE 'rejected'");
                }

                return;
            }

            $values = implode("','", $statuses);
            DB::statement('ALTER TABLE test_requests DROP CONSTRAINT IF EXISTS test_requests_status_check');
            DB::statement("ALTER TABLE test_requests ADD CONSTRAINT test_requests_status_check CHECK (status IN ('{$values}'))");
        }
    }
};
