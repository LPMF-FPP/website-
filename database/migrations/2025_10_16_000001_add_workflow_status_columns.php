<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SampleStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            if (!Schema::hasColumn('samples', 'status')) {
                $table->string('status')->default(SampleStatus::FORM_SUBMITTED->value)
                      ->after('test_request_id');
            }
        });

        Schema::table('sample_test_processes', function (Blueprint $table) {
            if (!Schema::hasColumn('sample_test_processes', 'performed_by')) {
                $table->foreignId('performed_by')->nullable()
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'status')) {
                $table->string('status')->default('penyerahan_pending')
                      ->after('request_id');
            }
            if (!Schema::hasColumn('deliveries', 'collected_at')) {
                $table->timestamp('collected_at')->nullable()
                      ->after('delivery_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            if (Schema::hasColumn('samples', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('sample_test_processes', function (Blueprint $table) {
            if (Schema::hasColumn('sample_test_processes', 'performed_by')) {
                $table->dropForeign(['performed_by']);
                $table->dropColumn('performed_by');
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('deliveries', 'status')) {
                $columns[] = 'status';
            }
            if (Schema::hasColumn('deliveries', 'collected_at')) {
                $columns[] = 'collected_at';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
