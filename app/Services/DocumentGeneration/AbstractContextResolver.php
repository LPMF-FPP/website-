<?php

namespace App\Services\DocumentGeneration;

use App\Enums\DocumentType;
use App\Services\DocumentGeneration\Contracts\DocumentContextResolver;

abstract class AbstractContextResolver implements DocumentContextResolver
{
    /**
     * Get common context data that all documents might need
     */
    protected function getCommonContext(): array
    {
        return [
            'generatedAt' => now(),
            'branding' => $this->getBrandingConfig(),
            'settings' => $this->getRelevantSettings(),
        ];
    }

    /**
     * Get branding configuration from settings
     */
    protected function getBrandingConfig(): array
    {
        // ✅ Convert filesystem path to web URL
        $logoPath = settings('branding.logo_path');
        $logoUrl = null;
        if ($logoPath) {
            // Check if it's already a URL
            if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
                $logoUrl = $logoPath;
            } elseif (str_starts_with($logoPath, '/')) {
                // Absolute web path
                $logoUrl = url($logoPath);
            } elseif (str_starts_with($logoPath, 'public/')) {
                // Laravel storage path
                $logoUrl = asset(str_replace('public/', 'storage/', $logoPath));
            } elseif (str_starts_with($logoPath, 'images/')) {
                // Public images path
                $logoUrl = asset($logoPath);
            } else {
                // Assume it's in public directory
                $logoUrl = asset('images/' . $logoPath);
            }
        }

        return [
            'institution_name' => settings('branding.institution_name', 'LPMF'),
            'logo_url' => $logoUrl,
            'logo_path' => $logoPath, // Keep original path for reference
            'address' => settings('branding.address'),
            'phone' => settings('branding.phone'),
            'email' => settings('branding.email'),
            'website' => settings('branding.website'),
        ];
    }

    /**
     * Get relevant settings for document rendering
     */
    protected function getRelevantSettings(): array
    {
        return [
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
            'locale' => config('app.locale', 'id'),
        ];
    }
}
