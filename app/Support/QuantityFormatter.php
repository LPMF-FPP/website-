<?php

namespace App\Support;

/**
 * Utility class for formatting quantities and appending units.
 *
 * Extracted from DeliveryController to eliminate code duplication.
 * Used whenever sample quantities need consistent display formatting.
 */
class QuantityFormatter
{
    /**
     * Format a numeric quantity for display.
     *
     * - Strips trailing zeros (e.g. "5.00" → "5", "5.50" → "5.5")
     * - Returns non-numeric values as-is (trimmed)
     * - Returns null for null/empty input
     */
    public static function formatQuantity(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return trim((string) $value) ?: null;
        }

        $number = (float) $value;
        $formatted = number_format($number, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? null : $formatted;
    }

    /**
     * Append a unit string to a formatted quantity.
     *
     * Returns null if quantity is null.
     * Returns quantity alone if unit is empty.
     */
    public static function appendUnit(?string $quantity, ?string $unit): ?string
    {
        if ($quantity === null) {
            return null;
        }

        $unit = $unit ? trim($unit) : '';

        return $unit !== '' ? $quantity.' '.$unit : $quantity;
    }
}
