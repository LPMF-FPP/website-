# Plan: Enforce Humidity Input & Alerts

> **Status:** Ready for Execution
> **Goal:** Make humidity mandatory for environment monitoring (Web & WhatsApp) and enable out-of-range triggers for WhatsApp input.

## 1. Web UI Changes

**File:** `app/Http/Controllers/EnvironmentMonitoringController.php`

- **Method:** `storeReading` & `storeCorrection`
- **Change:** Update validation rule for `humidity_rh` from `nullable` to `required`.

## 2. WhatsApp Command Upgrade

**File:** `app/Services/WhatsApp/Commands/TemperatureInputCommand.php`

- **Dependency:** Inject `EnvironmentMonitoringService` to use `detectOutOfRange`.
- **Parsing Logic:**
    - Require **two** numeric values from input.
    - First number = Temperature.
    - Second number = Humidity.
- **Save:** Store humidity value.
- **Feedback:**
    - Check for out-of-range conditions (Temp & Hum).
    - Append warnings to the WhatsApp reply (e.g., "⚠️ Peringatan: Kelembaban terlalu tinggi!").

## 3. Execution Steps

1.  **Modify Controller:** Update validation rules.
2.  **Modify Command:** Implement new parsing and alert logic.
3.  **Deploy:** Copy files to production.

---

**Ready to execute?** Type "Execute" to apply the changes.
