<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('qmh_templates', 'shell_mode')) {
                $table->string('shell_mode', 32)->nullable()->after('doc_type');
            }

            if (! Schema::hasColumn('qmh_templates', 'orientation_policy')) {
                $table->string('orientation_policy', 32)->nullable()->after('shell_mode');
            }

            if (! Schema::hasColumn('qmh_templates', 'show_signoff_footer')) {
                $table->boolean('show_signoff_footer')->nullable()->after('orientation_policy');
            }
        });

        DB::table('qmh_templates')
            ->where('doc_type', 'fr')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = json_decode((string) ($row->metadata ?? ''), true);
                    $metadata = is_array($metadata) ? $metadata : [];

                    $legacyProfile = strtolower(trim((string) ($metadata['layout_profile'] ?? 'structured_form')));
                    $shellMode = match ($legacyProfile) {
                        'declaration' => 'body_only',
                        default => 'full',
                    };
                    $orientationPolicy = match ($legacyProfile) {
                        'risk_matrix' => 'landscape',
                        default => 'portrait',
                    };
                    $showSignoffFooter = $legacyProfile !== 'declaration';

                    $nextShellMode = is_string($row->shell_mode) && trim((string) $row->shell_mode) !== ''
                        ? (string) $row->shell_mode
                        : $shellMode;
                    $nextOrientation = is_string($row->orientation_policy) && trim((string) $row->orientation_policy) !== ''
                        ? (string) $row->orientation_policy
                        : $orientationPolicy;
                    $nextShowSignoff = is_bool($row->show_signoff_footer)
                        ? $row->show_signoff_footer
                        : $showSignoffFooter;

                    $metadata['shell_mode'] = $nextShellMode;
                    $metadata['orientation_policy'] = $nextOrientation;
                    $metadata['show_signoff_footer'] = (bool) $nextShowSignoff;

                    DB::table('qmh_templates')
                        ->where('id', $row->id)
                        ->update([
                            'shell_mode' => $nextShellMode,
                            'orientation_policy' => $nextOrientation,
                            'show_signoff_footer' => (bool) $nextShowSignoff,
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('qmh_templates', function (Blueprint $table): void {
            $columns = [
                'shell_mode',
                'orientation_policy',
                'show_signoff_footer',
            ];

            $existing = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn('qmh_templates', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
