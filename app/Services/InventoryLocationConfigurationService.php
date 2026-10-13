<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryLocation;
use App\Models\InventoryLocationConfigurationEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLocationConfigurationService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InventoryLocationAccessService $locationAccess,
    ) {}

    /** @param array{name: string, location_type: string, is_restricted?: bool} $attributes */
    public function create(User $actor, array $attributes): InventoryLocation
    {
        return DB::transaction(function () use ($actor, $attributes): InventoryLocation {
            $requiredPermissions = ['inventori.create'];
            if ((bool) ($attributes['is_restricted'] ?? false)) {
                $requiredPermissions[] = 'inventori.location-access.manage';
            }
            $lockedActor = $this->lockAndAuthorizeActor($actor, $requiredPermissions);
            $this->assertCanonicalLocationType($attributes['location_type']);
            $location = InventoryLocation::query()->create([
                'name' => $attributes['name'],
                'location_type' => $attributes['location_type'],
                'is_restricted' => (bool) ($attributes['is_restricted'] ?? false),
            ]);

            InventoryLocationConfigurationEvent::query()->create([
                'location_id' => $location->id,
                'actor_user_id' => $lockedActor->id,
                'event_type' => 'created',
                'previous_configuration' => null,
                'new_configuration' => $this->snapshot($location),
                'occurred_at' => now(),
            ]);

            return $location;
        });
    }

    /** @param array{name: string, location_type: string} $attributes */
    public function update(User $actor, int $locationId, array $attributes): InventoryLocation
    {
        return DB::transaction(function () use ($actor, $locationId, $attributes): InventoryLocation {
            $lockedActor = $this->lockAndAuthorizeActor($actor, ['inventori.view', 'inventori.edit']);
            $location = InventoryLocation::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedView($actor, $location);
            $this->assertCanonicalLocationType($attributes['location_type']);

            $previousConfiguration = $this->snapshot($location);
            $location->fill(array_intersect_key($attributes, array_flip(['name', 'location_type'])));
            $newConfiguration = $this->snapshot($location);
            if ($previousConfiguration === $newConfiguration) {
                return $location;
            }

            $location->save();
            InventoryLocationConfigurationEvent::query()->create([
                'location_id' => $location->id,
                'actor_user_id' => $lockedActor->id,
                'event_type' => 'updated',
                'previous_configuration' => $previousConfiguration,
                'new_configuration' => $newConfiguration,
                'occurred_at' => now(),
            ]);

            return $location;
        });
    }

    private function lockAndAuthorizeActor(User $actor, string|array $permissionNames): User
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor konfigurasi lokasi tidak sesuai dengan pengguna yang terautentikasi.');
        }

        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        $permissionsGranted = $lockedActor && (is_string($permissionNames)
            ? $this->permissionService->userCanForMutation($lockedActor, $permissionNames)
            : $this->permissionService->userHasPermissionsForMutation($lockedActor, $permissionNames));

        if (! $lockedActor || ! $lockedActor->is_active || ! $permissionsGranted) {
            throw new AuthorizationException('Aktor tidak memiliki izin mengubah konfigurasi lokasi.');
        }

        return $lockedActor;
    }

    /** @return array{name: string, location_type: string, is_restricted: bool} */
    private function snapshot(InventoryLocation $location): array
    {
        return [
            'name' => (string) $location->name,
            'location_type' => (string) $location->location_type,
            'is_restricted' => (bool) $location->is_restricted,
        ];
    }

    private function assertCanonicalLocationType(string $locationType): void
    {
        if (! array_key_exists($locationType, InventoryLocation::LOCATION_TYPES)) {
            throw ValidationException::withMessages([
                'location_type' => 'Pilih tipe lokasi yang tersedia pada Master Lokasi.',
            ]);
        }
    }
}
