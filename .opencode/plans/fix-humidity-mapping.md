# Plan: Fix Humidity Mapping & Logic

> **Status:** Ready for Execution
> **Goal:** Fix the mismatch between code (`target_humidity_min`) and database (`target_hum_min`), and ensure humidity requirement is strictly controlled by the location settings (ignoring global default).

## 1. Model Fix (`EnvironmentLocation.php`)

- **Add Accessors:** Create `getTargetHumidityMinAttribute` and `Max` to Alias the DB columns `target_hum_min/max`.
- **Add Appends:** Ensure these aliases are included in JSON serialization (`$appends`).

## 2. Controller Fix (`EnvironmentMonitoringController.php`)

- **Map Inputs:** In `storeLocation` and `updateLocation`, manually map the input keys `target_humidity_min/max` to the DB column names `target_hum_min/max` before saving. This ensures data is actually persisted.

## 3. Logic Update (Service & WhatsApp)

- **Service (`EnvironmentMonitoringService.php`):** Update `validateReadingData`. Remove the check for global `$settings['humidity_enabled']`. Humidity should ONLY be required if the _Location_ has a target set (`target_humidity_min !== null`).
- **WhatsApp (`TemperatureInputCommand.php`):** Update the conditional check to match the Service logic (Location-specific only).

## 4. Execution Steps

1.  **Edit Model:** Add Accessors & Appends.
2.  **Edit Controller:** Fix saving logic.
3.  **Edit Service:** Update validation logic.
4.  **Edit Command:** Update validation logic.
5.  **Deploy:** Update production.

---

**Ready to execute?** Type "Execute" to proceed.
