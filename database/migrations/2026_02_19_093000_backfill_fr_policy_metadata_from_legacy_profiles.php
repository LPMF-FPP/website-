<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qmh_templates')) {
            return;
        }

        DB::table('qmh_templates')
            ->where('doc_type', 'fr')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = json_decode((string) ($row->metadata ?? ''), true);
                    $metadata = is_array($metadata) ? $metadata : [];

                    $legacyProfile = strtolower(trim((string) ($metadata['layout_profile'] ?? 'structured_form')));
                    $resolvedShellMode = match ($legacyProfile) {
                        'declaration' => 'body_only',
                        default => 'full',
                    };
                    $resolvedOrientation = match ($legacyProfile) {
                        'risk_matrix' => 'landscape',
                        default => 'portrait',
                    };
                    $resolvedSignoffFooter = $legacyProfile !== 'declaration';

                    $shellMode = is_string($row->shell_mode) && trim((string) $row->shell_mode) !== ''
                        ? (string) $row->shell_mode
                        : $resolvedShellMode;
                    $orientationPolicy = is_string($row->orientation_policy) && trim((string) $row->orientation_policy) !== ''
                        ? (string) $row->orientation_policy
                        : $resolvedOrientation;
                    $showSignoffFooter = is_bool($row->show_signoff_footer)
                        ? $row->show_signoff_footer
                        : $resolvedSignoffFooter;

                    $metadata['shell_mode'] = $shellMode;
                    $metadata['orientation_policy'] = $orientationPolicy;
                    $metadata['show_signoff_footer'] = (bool) $showSignoffFooter;

                    DB::table('qmh_templates')
                        ->where('id', $row->id)
                        ->update([
                            'shell_mode' => $shellMode,
                            'orientation_policy' => $orientationPolicy,
                            'show_signoff_footer' => (bool) $showSignoffFooter,
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Backfill migration is intentionally irreversible.
    }
};
