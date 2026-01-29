# Plan: Fix Manual Input UI for Humidity

> **Status:** Ready for Execution
> **Issue:** Manual input fails because the Humidity input field is hidden (server-side `@if`) based on global settings, even for locations that require humidity. This causes validation errors ("Kelembaban wajib diisi") because the backend enforces it per-location.
> **Fix:** Make the Humidity input visibility dynamic (client-side AlpineJS) based on the specific location's requirement.

## 1. View Modification (`index.blade.php`)

**A. Update Input Modal Trigger**
Pass a `hasHumidity` flag when opening the modal.

```html
<button
    @click="openInputModal(..., {{ $location->target_humidity_min !== null ? 'true' : 'false' }})"
></button>
```

**B. Update AlpineJS Logic**
Store `hasHumidity` in the modal state.

```javascript
openInputModal(id, name, hasHumidity) {
    this.inputModal.hasHumidity = hasHumidity;
    // ...
}
```

**C. Update Input Visibility**
Replace Blade `@if` with Alpine `x-show`.

```html
<div x-show="inputModal.hasHumidity">
    <label>Kelembaban (%RH)</label>
    <input ... />
</div>
```

_Note:_ We can also include `{{ $settings['humidity_enabled'] }}` in the condition if global setting should also force it show.

## 2. Execution Steps

1.  **Edit `index.blade.php`**: Implement the changes above.
2.  **Deploy**: Update production.
3.  **Clear Cache**: `view:clear`.

---

**Ready to execute?** Type "Execute".
