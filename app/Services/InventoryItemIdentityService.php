<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryItemIdentityEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryItemIdentityService
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public static function canonicalize(string $itemCode): string
    {
        return strtoupper(trim($itemCode, ' '));
    }

    public function hasHistory(InventoryItem $item): bool
    {
        return $item->balances()->exists()
            || $item->lots()->exists()
            || $item->movements()->exists();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): InventoryItem
    {
        return DB::transaction(function () use ($attributes, $actor): InventoryItem {
            $this->lockAndAuthorizeActor($actor, 'inventori.create');
            $itemCode = self::canonicalize((string) ($attributes['item_code'] ?? ''));
            $this->validateItemCode($itemCode);

            try {
                return InventoryItem::query()->create([
                    ...$attributes,
                    'item_code' => $itemCode,
                ]);
            } catch (QueryException $exception) {
                $this->throwIfItemCodeCollision($exception);
                throw $exception;
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(int $itemId, array $attributes, User $actor, ?string $reason = null): InventoryItem
    {
        try {
            return DB::transaction(function () use ($itemId, $attributes, $actor, $reason): InventoryItem {
                $this->lockAndAuthorizeActor($actor, 'inventori.edit');
                $item = InventoryItem::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                $itemCode = self::canonicalize((string) ($attributes['item_code'] ?? ''));
                $this->validateItemCode($itemCode, $item->id);
                $hasHistory = $this->hasHistory($item);
                $codeChanged = $item->item_code !== $itemCode;

                if ($codeChanged && $hasHistory && trim((string) $reason) === '') {
                    throw ValidationException::withMessages([
                        'identity_change_reason' => 'Alasan perubahan kode wajib diisi karena item sudah memiliki riwayat stok.',
                    ]);
                }

                $previousCode = $item->item_code;
                $item->fill(collect($attributes)->except('item_code')->all());
                $item->item_code = $itemCode;

                try {
                    $item->save();
                } catch (QueryException $exception) {
                    $this->throwIfItemCodeCollision($exception);
                    throw $exception;
                }

                if ($codeChanged && $hasHistory) {
                    InventoryItemIdentityEvent::query()->create([
                        'inventory_item_id' => $item->id,
                        'actor_user_id' => $actor->id,
                        'previous_item_code' => $previousCode,
                        'new_item_code' => $itemCode,
                        'event_type' => $previousCode === null ? 'assigned' : 'corrected',
                        'reason' => trim((string) $reason),
                        'occurred_at' => now(),
                    ]);
                }

                return $item;
            });
        } catch (QueryException $exception) {
            $this->throwIfItemCodeCollision($exception);
            throw $exception;
        }
    }

    private function lockAndAuthorizeActor(User $actor, string $permissionName): void
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor perubahan item tidak sesuai dengan pengguna yang terautentikasi.');
        }

        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userCanForMutation($lockedActor, $permissionName)) {
            throw new AuthorizationException('Aktor tidak memiliki izin mengubah identitas item.');
        }
    }

    private function validateItemCode(string $itemCode, ?int $ignoreId = null): void
    {
        $uniqueRule = Rule::unique('inventory_items', 'item_code');
        if ($ignoreId !== null) {
            $uniqueRule->ignore($ignoreId);
        }

        $validator = Validator::make(
            ['item_code' => $itemCode],
            [
                'item_code' => [
                    'required',
                    'string',
                    'max:64',
                    'regex:/\A[A-Z0-9._-]+\z/',
                    $uniqueRule,
                ],
            ],
            [
                'item_code.regex' => 'Kode item hanya boleh berisi huruf ASCII, angka, titik, garis bawah, dan tanda hubung.',
                'item_code.unique' => 'Kode item sudah digunakan item lain.',
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function throwIfItemCodeCollision(QueryException $exception): void
    {
        if ($exception->getCode() === '23505' && str_contains($exception->getMessage(), 'inventory_items_item_code_unique')) {
            throw ValidationException::withMessages([
                'item_code' => 'Kode item sudah digunakan item lain.',
            ]);
        }
    }
}
