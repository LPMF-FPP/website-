# Debugging Plan: Manual Temperature Input Failure

> **Status:** Proposal (Read-Only)
> **Goal:** Investigate and fix the issue where manual temperature input fails on the web UI (`/monitoring/environment`), while WhatsApp webhook input works correctly.

## 1. Analysis

- **Symptom:** Manual input fails (User states "cannot input manually"). WhatsApp works.
- **Context:**
    - We recently modified `EnvironmentMonitoringController` and `TemperatureInputCommand`.
    - We changed `humidity_rh` validation to `nullable` in the controller.
    - We updated the `TemperatureInputCommand` to handle optional humidity logic.
- **Hypothesis:**
    1.  **Frontend Logic:** The AlpineJS logic in `index.blade.php` (or similar view) might not be sending the correct payload structure expected by the updated controller.
    2.  **Controller Logic:** `storeReading` in `EnvironmentMonitoringController` might have a logic error when handling the input manually vs what the service expects.
    3.  **Validation:** Maybe the `location_id` is missing or invalid in the manual form payload.

## 2. Investigation Steps

### A. Inspect Frontend Code

- **File:** `resources/views/monitoring/environment/index.blade.php` (or wherever the manual input modal is).
- **Action:** Check the `submitReading()` JavaScript/AlpineJS function.
    - Does it send `humidity_rh` as `null` or `''`?
    - Does it handle the response correctly?
    - What payload is sent?

### B. Inspect Backend Code

- **File:** `app/Http/Controllers/EnvironmentMonitoringController.php`
- **Action:** Check `storeReading`.
    - It calls `$this->service->validateReadingData($location, $validated)`.
    - Does `validateReadingData` in `EnvironmentMonitoringService` behave correctly for manual input?

### C. Check Logs (If possible)

- User didn't provide a stack trace, but "cannot input" often means a 422 Validation Error or 500 Server Error.
- The previous `TemperatureInputCommand` fix involved `ReadingSource::MANUAL`. Does the Controller also need to use this Enum?
    - Controller uses `$validated` which might not include `source`.
    - `EnvironmentMonitoringService::createReading` uses `$data['source'] ?? ReadingSource::MANUAL`. This seems safe.
    - **Wait**, `entered_by` is required in DB (`NOT NULL`).
    - WhatsApp command sets `entered_by` explicitly.
    - Does `storeReading` set `entered_by`?
    - `createReading` takes `User $user` as 3rd arg.
    - `storeReading` calls `$this->service->createReading($location, $validated, $request->user())`.
    - This should be fine _if_ user is logged in.

## 3. Potential Fixes

1.  **Frontend Payload:** Ensure `humidity_rh` is sent as `null` if empty, not empty string `""` (which might fail `numeric` validation or service logic).
2.  **Service Validation:** Ensure `validateReadingData` doesn't reject valid manual input.

## 4. Execution Plan

1.  **Read** `resources/views/monitoring/environment/index.blade.php` to see the form submission logic.
2.  **Read** `app/Http/Controllers/EnvironmentMonitoringController.php` to confirm `storeReading` implementation.
3.  **Propose Fix.**

---

**Ready to execute investigation?** Type "Execute" to start reading the files.
