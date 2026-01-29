# Plan: Humidity Toggle & WhatsApp Logic

> **Status:** Ready for Execution
> **Goal:** Add a UI toggle to enable/disable humidity monitoring per location, and ensure WhatsApp bot respects this setting.

## 1. Web UI: Location Management

**File:** `resources/views/monitoring/environment/manage.blade.php`

**Implementation:**

1.  **Add Toggle:** Add "Monitor Kelembaban" checkbox in the modal form.
2.  **AlpineJS Logic:**
    - **Init:** Set toggle `true` if `target_humidity_min` or `max` exists.
    - **Submit:** If toggle is `false`, force send `null` for humidity targets.
    - **Visibility:** Use `x-show` to hide/show humidity inputs based on toggle.

## 2. WhatsApp Logic Verification

**File:** `app/Services/WhatsApp/Commands/TemperatureInputCommand.php`

**Logic Check:**
Ensure the validation logic strictly follows the location settings:

```php
// If only 1 number provided (Humidity is null):
if ($humidity === null) {
    // Check if Location requires humidity
    // REQUIRED condition: Global 'humidity_enabled' IS ON OR Location has a target min
    if ($settings['humidity_enabled'] || $location->target_humidity_min !== null) {
        return "⚠️ Kelembaban wajib diisi...";
    }
    // Else: Allow null
}
```

_Note:_ This logic is already consistent with the proposed UI change (clearing targets = disabling requirement), assuming global `humidity_enabled` is false or used as a hard override.

## 3. Execution Steps

1.  **Edit View:** Update `manage.blade.php`.
2.  **Verify/Edit Command:** Ensure `TemperatureInputCommand.php` reflects the logic above.
3.  **Deploy:** Copy updates to production.

---

**Ready to execute?** Type "Execute" to proceed.
