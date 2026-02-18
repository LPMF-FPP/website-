<?php

declare(strict_types=1);

namespace App\Http\Requests\Quality\Concerns;

use App\Support\QmhFrLayoutProfile;

trait HandlesQmhTemplateLayoutConfig
{
    protected function mergeRiskMatrixColumnsFromCsv(): void
    {
        $rawColumns = $this->input('risk_matrix_columns_csv');
        if (! is_string($rawColumns) || trim($rawColumns) === '') {
            return;
        }

        $columns = array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $rawColumns)
        ), static fn (string $item): bool => $item !== ''));

        $this->merge([
            'risk_matrix_columns' => $columns,
        ]);
    }

    protected function validateLayoutConfig($validator): void
    {
        $docType = (string) $this->input('doc_type', '');
        $isFr = $docType === 'fr';

        if (! $isFr) {
            foreach (['layout_profile', 'shell_mode', 'orientation_policy', 'show_signoff_footer', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns_csv', 'risk_matrix_columns'] as $field) {
                $value = $this->input($field);
                $hasValue = is_string($value) ? trim($value) !== '' : ! is_null($value);
                if ($hasValue) {
                    $validator->errors()->add($field, 'Konfigurasi layout/logo hanya berlaku untuk template FR.');
                }
            }

            return;
        }

        $logoSource = (string) $this->input('logo_source', QmhFrLayoutProfile::defaults()['logo_source']);
        $logoPath = (string) $this->input('logo_path', '');
        if ($logoSource === 'custom' && trim($logoPath) === '') {
            $validator->errors()->add('logo_path', 'Logo custom wajib diisi jika sumber logo adalah custom.');
        }

        $rawLayoutProfile = $this->input('layout_profile');
        $layoutProfile = is_string($rawLayoutProfile) && trim($rawLayoutProfile) !== ''
            ? strtolower(trim($rawLayoutProfile))
            : null;

        $riskColumns = $this->input('risk_matrix_columns');
        if ($layoutProfile === 'risk_matrix' && (! is_array($riskColumns) || count($riskColumns) < 2)) {
            $validator->errors()->add('risk_matrix_columns_csv', 'Kolom risk matrix minimal 2 kolom dipisahkan koma.');
        }

        $shellMode = QmhFrLayoutProfile::normalizeShellMode((string) $this->input('shell_mode', ''));
        $showSignoffFooter = QmhFrLayoutProfile::normalizeShowSignoffFooter($this->input('show_signoff_footer'));
        if ($shellMode === 'body_only' && $showSignoffFooter) {
            $validator->errors()->add('show_signoff_footer', 'Footer sign-off harus dimatikan saat shell mode body_only.');
        }
    }

    protected function sanitizeFrLayoutConfigInput(): void
    {
        if ((string) $this->input('doc_type', '') !== 'fr') {
            return;
        }

        $rawLayoutProfile = $this->input('layout_profile');
        if (! is_string($rawLayoutProfile)) {
            return;
        }

        if (trim($rawLayoutProfile) === '') {
            $this->merge([
                'layout_profile' => null,
            ]);
            $layoutProfile = QmhFrLayoutProfile::defaultAuthoringProfile();
        } else {
            $layoutProfile = strtolower(trim($rawLayoutProfile));
            if (! in_array($layoutProfile, QmhFrLayoutProfile::allowedProfiles(), true)) {
                $layoutProfile = QmhFrLayoutProfile::defaultAuthoringProfile();
            }
        }

        $legacyDefaults = match ($layoutProfile) {
            'declaration' => ['shell_mode' => 'body_only', 'orientation_policy' => 'portrait', 'show_signoff_footer' => false],
            'risk_matrix' => ['shell_mode' => 'full', 'orientation_policy' => 'landscape', 'show_signoff_footer' => true],
            default => ['shell_mode' => 'full', 'orientation_policy' => 'portrait', 'show_signoff_footer' => true],
        };

        $payload = [
            'layout_profile' => $layoutProfile,
            'shell_mode' => QmhFrLayoutProfile::normalizeShellMode(
                is_string($this->input('shell_mode'))
                    ? (string) $this->input('shell_mode')
                    : $legacyDefaults['shell_mode']
            ),
            'orientation_policy' => QmhFrLayoutProfile::normalizeOrientationPolicy(
                is_string($this->input('orientation_policy'))
                    ? (string) $this->input('orientation_policy')
                    : $legacyDefaults['orientation_policy']
            ),
            'show_signoff_footer' => QmhFrLayoutProfile::normalizeShowSignoffFooter(
                $this->input('show_signoff_footer', $legacyDefaults['show_signoff_footer'])
            ),
        ];

        if ($layoutProfile !== 'declaration') {
            $payload['declaration_header'] = null;
        }

        if ($layoutProfile !== 'risk_matrix') {
            $payload['risk_matrix_columns_csv'] = null;
            $payload['risk_matrix_columns'] = null;
        }

        $this->merge($payload);
    }
}
