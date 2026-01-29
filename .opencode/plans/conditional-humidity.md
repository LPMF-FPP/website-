# Plan: Conditional Humidity & AM/PM Input

> **Status:** Ready for Execution
> **Goal:**
>
> 1. Make humidity input optional by default (unless required by location).
> 2. Change time keywords from "pagi/siang" to "am/pm".

## 1. Web UI (Controller) Update

**File:** `app/Http/Controllers/EnvironmentMonitoringController.php`

- **Change:** Revert `humidity_rh` validation rule to `nullable`.
    - `'humidity_rh' => ['nullable', 'numeric', ...]`
- **Reason:** Service layer handles conditional requirement.

## 2. WhatsApp Command Update

**File:** `app/Services/WhatsApp/Commands/TemperatureInputCommand.php`

### A. Keyword Parsing (AM/PM)

- Detect `am` or `pm` (case-insensitive).
- Map:
    - `am` -> Morning (08:00)
    - `pm` -> Afternoon (14:00)
- _Note:_ Replace `pagi/siang/sore` logic.

### B. Humidity Parsing

- Identify numeric values in command parameters.
- **1 Number found:**
    - `Temp` = Number 1
    - `Humidity` = `null`
- **2 Numbers found:**
    - `Temp` = Number 1
    - `Humidity` = Number 2

### C. Conditional Validation

- Retrieve Location settings (check `target_humidity_min` or global `humidity_enabled`).
- **Logic:**
    - If `Location Requires Humidity` AND `Humidity is Null` -> **Return Error**: "⚠️ Kelembaban wajib diisi untuk lokasi ini."
    - Else -> Save data (with `null` humidity if strictly optional).

### D. Alerting

- Run `detectOutOfRange`.
- Append warnings to success message if any.

## 3. Execution Steps

1.  **Modify Controller:** Update validation.
2.  **Modify Command:** Rewrite `execute` logic for AM/PM and optional humidity.
3.  **Deploy:** Update production.

---

**Ready to execute?** Type "Execute" to proceed.
