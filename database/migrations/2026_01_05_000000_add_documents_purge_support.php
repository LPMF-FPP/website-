<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && !Schema::hasTable('settings')) {
            Schema::rename('system_settings', 'settings');
        }

        if (Schema::hasTable('sequences') && !Schema::hasTable('number_sequences')) {
            Schema::rename('sequences', 'number_sequences');
        }

        if (Schema::hasTable('number_sequences')) {
            Schema::table('number_sequences', function (Blueprint $table) {
                if (!Schema::hasColumn('number_sequences', 'reset_period')) {
                    $table->string('reset_period', 32)->default('never')->after('bucket');
                }
            });
        }

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'storage_disk')) {
                $table->string('storage_disk', 32)->default('public')->after('source');
            }

            if (!Schema::hasColumn('documents', 'deleted_at')) {
                $table->softDeletes();
            }

            $table->index('document_type', 'documents_document_type_index');
            $table->index('created_at', 'documents_created_at_index');
            $table->index(['document_type', 'created_at'], 'documents_doc_type_created_index');
            $table->index(['source', 'created_at'], 'documents_source_created_index');
        });

        DB::table('documents')
            ->whereNull('storage_disk')
            ->update(['storage_disk' => 'public']);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'storage_disk')) {
                $table->dropColumn('storage_disk');
            }

            if (Schema::hasColumn('documents', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            $table->dropIndex('documents_document_type_index');
            $table->dropIndex('documents_created_at_index');
            $table->dropIndex('documents_doc_type_created_index');
            $table->dropIndex('documents_source_created_index');
        });

        if (Schema::hasTable('number_sequences') && Schema::hasColumn('number_sequences', 'reset_period')) {
            Schema::table('number_sequences', function (Blueprint $table) {
                $table->dropColumn('reset_period');
            });
        }

        if (Schema::hasTable('number_sequences') && !Schema::hasTable('sequences')) {
            Schema::rename('number_sequences', 'sequences');
        }

        if (Schema::hasTable('settings') && !Schema::hasTable('system_settings')) {
            Schema::rename('settings', 'system_settings');
        }
    }
};
