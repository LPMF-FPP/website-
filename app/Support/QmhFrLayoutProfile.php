<?php

declare(strict_types=1);

namespace App\Support;

final class QmhFrLayoutProfile
{
    public static function defaultAuthoringProfile(): string
    {
        return 'structured_form';
    }

    /**
     * @return array<int, string>
     */
    public static function allowedProfiles(): array
    {
        return ['declaration', 'risk_matrix', 'structured_form'];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedLogoSources(): array
    {
        return ['settings', 'custom', 'default'];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedShellModes(): array
    {
        return ['full', 'body_only', 'none'];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedOrientationPolicies(): array
    {
        return ['portrait', 'landscape'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'layout_profile' => self::defaultAuthoringProfile(),
            'shell_mode' => 'full',
            'orientation_policy' => 'portrait',
            'show_signoff_footer' => true,
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

        if (in_array($value, ['table', 'risk_matrix'], true)) {
            return 'risk_matrix';
        }

        if (in_array($value, ['non_table', 'structured_form', 'declaration'], true)) {
            return 'structured_form';
        }

        return in_array($value, self::allowedProfiles(), true)
            ? $value
            : self::defaultAuthoringProfile();
    }

    public static function normalizeRuntimeProfile(?string $profile): string
    {
        $value = strtolower(trim((string) $profile));

        if (in_array($value, ['table', 'risk_matrix'], true)) {
            return 'risk_matrix';
        }

        if (in_array($value, ['non_table', 'structured_form', 'declaration'], true)) {
            return 'structured_form';
        }

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

    public static function normalizeShellMode(?string $shellMode): string
    {
        $value = strtolower(trim((string) $shellMode));

        return in_array($value, self::allowedShellModes(), true)
            ? $value
            : (string) self::defaults()['shell_mode'];
    }

    public static function normalizeOrientationPolicy(?string $orientationPolicy): string
    {
        $value = strtolower(trim((string) $orientationPolicy));

        return in_array($value, self::allowedOrientationPolicies(), true)
            ? $value
            : (string) self::defaults()['orientation_policy'];
    }

    public static function normalizeShowSignoffFooter(mixed $showSignoffFooter): bool
    {
        if (is_bool($showSignoffFooter)) {
            return $showSignoffFooter;
        }

        if (is_int($showSignoffFooter)) {
            return $showSignoffFooter === 1;
        }

        if (is_string($showSignoffFooter)) {
            $value = strtolower(trim($showSignoffFooter));

            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return (bool) self::defaults()['show_signoff_footer'];
    }

    public static function runtimeProfileFromMetadata(array $metadata): string
    {
        if (! array_key_exists('layout_profile', $metadata)) {
            return 'legacy';
        }

        $value = is_string($metadata['layout_profile'] ?? null)
            ? $metadata['layout_profile']
            : null;

        return self::normalizeRuntimeProfile($value);
    }

    public static function shouldRenderFrShell(?string $profile): bool
    {
        $resolved = self::normalizeRuntimeProfile($profile);

        return match ($resolved) {
            'declaration' => false,
            default => true,
        };
    }

    public static function shouldRenderFrShellFromPolicy(?string $shellMode): bool
    {
        return self::normalizeShellMode($shellMode) === 'full';
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
        $normalizedProfile = self::normalizeProfile(
            array_key_exists('layout_profile', $metadata) && is_string($metadata['layout_profile'])
                ? $metadata['layout_profile']
                : null
        );
        $finalPolicy = self::legacyFinalPolicyFromProfile($normalizedProfile);

        return [
            'layout_profile' => $normalizedProfile,
            'shell_mode' => (string) $finalPolicy['shell_mode'],
            'orientation_policy' => (string) $finalPolicy['orientation_policy'],
            'show_signoff_footer' => (bool) $finalPolicy['show_signoff_footer'],
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
        return self::fromExplicitMetadata($schema);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function fromExplicitMetadata(array $metadata): array
    {
        $resolved = [];

        if (array_key_exists('layout_profile', $metadata)) {
            $resolved['layout_profile'] = self::normalizeProfile(is_string($metadata['layout_profile']) ? $metadata['layout_profile'] : null);
        }

        if (array_key_exists('logo_source', $metadata)) {
            $resolved['logo_source'] = self::normalizeLogoSource(is_string($metadata['logo_source']) ? $metadata['logo_source'] : null);
        }

        if (array_key_exists('shell_mode', $metadata)) {
            $resolved['shell_mode'] = self::normalizeShellMode(is_string($metadata['shell_mode']) ? $metadata['shell_mode'] : null);
        }

        if (array_key_exists('orientation_policy', $metadata)) {
            $resolved['orientation_policy'] = self::normalizeOrientationPolicy(is_string($metadata['orientation_policy']) ? $metadata['orientation_policy'] : null);
        }

        if (array_key_exists('show_signoff_footer', $metadata)) {
            $resolved['show_signoff_footer'] = self::normalizeShowSignoffFooter($metadata['show_signoff_footer']);
        }

        if (array_key_exists('logo_path', $metadata)) {
            $resolved['logo_path'] = self::normalizeNullableString($metadata['logo_path'] ?? null, 255);
        }

        if (array_key_exists('declaration_header', $metadata)) {
            $resolved['declaration_header'] = self::normalizeNullableString($metadata['declaration_header'] ?? null, 255);
        }

        if (array_key_exists('risk_matrix_columns', $metadata)) {
            $resolved['risk_matrix_columns'] = self::normalizeRiskMatrixColumns($metadata['risk_matrix_columns'] ?? null);
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
        $schema['shell_mode'] = $resolved['shell_mode'];
        $schema['orientation_policy'] = $resolved['orientation_policy'];
        $schema['show_signoff_footer'] = $resolved['show_signoff_footer'];
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
        $profile = self::normalizeProfile(is_string($validated['layout_profile'] ?? null) ? $validated['layout_profile'] : null);
        $legacyFinalPolicy = self::legacyFinalPolicyFromProfile($profile);

        $resolved = [
            'layout_profile' => $profile,
            'shell_mode' => (string) $legacyFinalPolicy['shell_mode'],
            'orientation_policy' => (string) $legacyFinalPolicy['orientation_policy'],
            'show_signoff_footer' => (bool) $legacyFinalPolicy['show_signoff_footer'],
            'logo_source' => self::normalizeLogoSource(is_string($validated['logo_source'] ?? null) ? $validated['logo_source'] : null),
            'logo_path' => self::normalizeNullableString($validated['logo_path'] ?? null, 255),
        ];

        if ($profile === 'risk_matrix') {
            $resolved['risk_matrix_columns'] = self::normalizeRiskMatrixColumns($validated['risk_matrix_columns'] ?? null);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function identityKey(array $metadata): string
    {
        $resolved = self::fromMetadata($metadata);

        return implode('|', [
            (string) $resolved['shell_mode'],
            (string) $resolved['orientation_policy'],
            (bool) $resolved['show_signoff_footer'] ? '1' : '0',
        ]);
    }

    /**
     * @return array{shell_mode: string, orientation_policy: string, show_signoff_footer: bool}
     */
    private static function legacyFinalPolicyFromProfile(?string $profile): array
    {
        $resolvedProfile = self::normalizeProfile($profile);

        return match ($resolvedProfile) {
            'risk_matrix' => [
                'shell_mode' => 'full',
                'orientation_policy' => 'landscape',
                'show_signoff_footer' => true,
            ],
            'structured_form', 'declaration' => [
                'shell_mode' => 'none',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => false,
            ],
            default => [
                'shell_mode' => 'full',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => true,
            ],
        };
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
