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
            foreach (['layout_profile', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns_csv'] as $field) {
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

        $layoutProfile = (string) $this->input('layout_profile', QmhFrLayoutProfile::defaults()['layout_profile']);
        $riskColumns = $this->input('risk_matrix_columns');
        if ($layoutProfile === 'risk_matrix' && (! is_array($riskColumns) || count($riskColumns) < 2)) {
            $validator->errors()->add('risk_matrix_columns_csv', 'Kolom risk matrix minimal 2 kolom dipisahkan koma.');
        }
    }
}
