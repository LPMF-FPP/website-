<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappWhitelist;
use Illuminate\Support\Collection;

class WhitelistService
{
    /**
     * Check if a phone number is allowed to access admin commands.
     */
    public function isAllowed(string $phoneNumber): bool
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        // Always allow super admin
        if ($this->isSuperAdmin($normalized)) {
            return true;
        }

        // Check database whitelist
        return WhatsappWhitelist::where('phone_number', $normalized)->exists();
    }

    /**
     * Check if a phone number is the super admin.
     */
    public function isSuperAdmin(string $phoneNumber): bool
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);
        $superAdmin = settings('notifications.whatsapp.admin_number', '6285956592404');

        return $normalized === $this->normalizePhoneNumber($superAdmin);
    }

    /**
     * Add a phone number to the whitelist.
     */
    public function add(string $phoneNumber, ?string $name = null, ?string $addedBy = null): WhatsappWhitelist
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        return WhatsappWhitelist::updateOrCreate(
            ['phone_number' => $normalized],
            [
                'name' => $name,
                'added_by' => $addedBy,
            ]
        );
    }

    /**
     * Remove a phone number from the whitelist.
     */
    public function remove(string $phoneNumber): bool
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        return WhatsappWhitelist::where('phone_number', $normalized)->delete() > 0;
    }

    /**
     * Get all whitelisted numbers.
     */
    public function getAll(): Collection
    {
        return WhatsappWhitelist::orderBy('name')->get();
    }

    /**
     * Get all admin phone numbers that should receive admin alerts.
     *
     * Includes: all whitelisted numbers + super admin fallback.
     *
     * @return array<int, string>
     */
    public function getAdminPhoneNumbers(): array
    {
        $numbers = $this->getAll()
            ->pluck('phone_number')
            ->filter()
            ->map(fn ($n) => $this->normalizePhoneNumber((string) $n))
            ->values()
            ->toArray();

        $superAdmin = $this->normalizePhoneNumber(
            (string) settings('notifications.whatsapp.admin_number', '6285956592404')
        );

        $numbers[] = $superAdmin;

        return array_values(array_unique($numbers));
    }

    /**
     * Normalize phone number (remove @s.whatsapp.net, +, etc).
     */
    public function normalizePhoneNumber(string $jidOrPhone): string
    {
        // Remove JID suffix if present
        if (str_contains($jidOrPhone, '@')) {
            $jidOrPhone = explode('@', $jidOrPhone)[0];
        }

        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $jidOrPhone);

        // Ensure proper format (62xxx)
        if (str_starts_with($phone, '08')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }
}
