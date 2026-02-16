<?php

declare(strict_types=1);

namespace App\Support;

final class QmhFrLayoutProfile
{
    /**
     * @return array<int, string>
     */
    public static function allowedProfiles(): array
    {
        return ['declaration', 'risk_matrix'];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedLogoSources(): array
    {
        return ['settings', 'custom', 'default'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'layout_profile' => 'declaration',
            'logo_source' => 'settings',
            'logo_path' => null,
            'declaration_header' => null,
            'risk_matrix_columns' => [
                'Aspek Risiko',
                'Nilai Risiko',
                'Keterangan',
            ],
        ];
    }

    public static function normalizeProfile(?string $profile): string
    {
        $value = strtolower(trim((string) $profile));

        return in_array($value, self::allowedProfiles(), true)
            ? $value
            : (string) self::defaults()['layout_profile'];
    }

    public static function normalizeRuntimeProfile(?string $profile): string
    {
        $value = strtolower(trim((string) $profile));

        return in_array($value, ['legacy', ...self::allowedProfiles()], true)
            ? $value
            : 'legacy';
    }

    public static function normalizeLogoSource(?string $logoSource): string
    {
        $value = strtolower(trim((string) $logoSource));

        return in_array($value, self::allowedLogoSources(), true)
            ? $value
            : (string) self::defaults()['logo_source'];
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeRiskMatrixColumns(mixed $columns): array
    {
        $defaults = (array) self::defaults()['risk_matrix_columns'];
        if (! is_array($columns)) {
            return $defaults;
        }

        $normalized = [];
        foreach ($columns as $column) {
            if (! is_string($column)) {
                continue;
            }

            $value = trim($column);
            if ($value === '') {
                continue;
            }

            $normalized[] = mb_substr($value, 0, 80);
        }

        $normalized = array_values(array_unique($normalized));

        if (count($normalized) < 2) {
            return $defaults;
        }

        return array_slice($normalized, 0, 6);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function fromMetadata(array $metadata): array
    {
        return [
            'layout_profile' => self::normalizeProfile((string) ($metadata['layout_profile'] ?? null)),
            'logo_source' => self::normalizeLogoSource((string) ($metadata['logo_source'] ?? null)),
            'logo_path' => self::normalizeNullableString($metadata['logo_path'] ?? null, 255),
            'declaration_header' => self::normalizeNullableString($metadata['declaration_header'] ?? null, 255),
            'risk_matrix_columns' => self::normalizeRiskMatrixColumns($metadata['risk_matrix_columns'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function fromSchema(array $schema): array
    {
        $resolved = [];

        if (array_key_exists('layout_profile', $schema)) {
            $resolved['layout_profile'] = self::normalizeProfile(is_string($schema['layout_profile']) ? $schema['layout_profile'] : null);
        }

        if (array_key_exists('logo_source', $schema)) {
            $resolved['logo_source'] = self::normalizeLogoSource(is_string($schema['logo_source']) ? $schema['logo_source'] : null);
        }

        if (array_key_exists('logo_path', $schema)) {
            $resolved['logo_path'] = self::normalizeNullableString($schema['logo_path'] ?? null, 255);
        }

        if (array_key_exists('declaration_header', $schema)) {
            $resolved['declaration_header'] = self::normalizeNullableString($schema['declaration_header'] ?? null, 255);
        }

        if (array_key_exists('risk_matrix_columns', $schema)) {
            $resolved['risk_matrix_columns'] = self::normalizeRiskMatrixColumns($schema['risk_matrix_columns'] ?? null);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public static function applyToSchema(array $schema, array $profile): array
    {
        $resolved = self::fromMetadata($profile);
        $schema['layout_profile'] = $resolved['layout_profile'];
        $schema['logo_source'] = $resolved['logo_source'];
        $schema['logo_path'] = $resolved['logo_path'];
        $schema['declaration_header'] = $resolved['declaration_header'];
        $schema['risk_matrix_columns'] = $resolved['risk_matrix_columns'];

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function fromValidatedTemplateInput(array $validated): array
    {
        return self::fromMetadata([
            'layout_profile' => $validated['layout_profile'] ?? null,
            'logo_source' => $validated['logo_source'] ?? null,
            'logo_path' => $validated['logo_path'] ?? null,
            'declaration_header' => $validated['declaration_header'] ?? null,
            'risk_matrix_columns' => $validated['risk_matrix_columns'] ?? null,
        ]);
    }

    private static function normalizeNullableString(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $max);
    }
}
