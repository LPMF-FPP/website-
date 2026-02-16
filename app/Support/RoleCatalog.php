<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class RoleCatalog
{
    /**
     * @var array<int, string>
     */
    private const CORE_STAFF_ROLES = [
        'analis',
        'penyelia',
        'manajer_teknis',
        'admin',
    ];

    /**
     * @return array<int, string>
     */
    public function coreStaffRoles(): array
    {
        return self::CORE_STAFF_ROLES;
    }

    public function normalize(string $role): string
    {
        return Str::of($role)
            ->lower()
            ->trim()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    public function staffRoles(): array
    {
        $configured = settings('security.available_roles', self::CORE_STAFF_ROLES);
        if (! is_array($configured)) {
            $configured = [];
        }

        return collect($configured)
            ->map(fn ($role) => is_string($role) ? $this->normalize($role) : '')
            ->filter()
            ->merge(self::CORE_STAFF_ROLES)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function allKnownRoles(): array
    {
        $databaseRoles = User::query()
            ->select('role')
            ->whereNotNull('role')
            ->distinct()
            ->pluck('role')
            ->map(fn ($role) => is_string($role) ? $this->normalize($role) : '')
            ->filter()
            ->values()
            ->all();

        return collect($this->staffRoles())
            ->merge($databaseRoles)
            ->unique()
            ->values()
            ->all();
    }
}
