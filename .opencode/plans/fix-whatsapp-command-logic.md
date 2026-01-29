# Plan: Fix WhatsApp Temperature Input Logic

> **Status:** Ready for Execution
> **Issue:** Command fails with "Command execution error" due to Enum validation failure (`"whatsapp" is not a valid backing value`) and likely `entered_by` Not Null constraint violation.
> **Fix:** Use valid `ReadingSource::MANUAL` enum and ensure `entered_by` is populated with a valid User ID.

---

### 1. Analysis

- **Error 1:** `ReadingSource` Enum does not have a `WHATSAPP` case. Database check constraint restricts values to `manual`, `import`, `iot`.
    - _Solution:_ Use `ReadingSource::MANUAL` and add details to `notes`.
- **Error 2:** `entered_by` column is `NOT NULL` in database, but code passes `null`.
    - _Solution:_ Lookup user by phone number. If not found, fallback to Location PIC or Admin User (ID 443).

### 2. Implementation Details

**File:** `app/Services/WhatsApp/Commands/TemperatureInputCommand.php`

**Changes:**

1.  Add Imports:
    ```php
    use App\Enums\ReadingSource;
    use App\Models\User;
    ```
2.  User Lookup Logic:
    ```php
    $user = User::where('phone', $phone)->first();
    $enteredBy = $user?->id ?? $location->pic_user_id ?? 443; // 443 = Admin LPMF fallback
    ```
3.  Create Reading:
    ```php
    EnvironmentReading::create([
        // ...
        'entered_by' => $enteredBy,
        'source' => ReadingSource::MANUAL, // Fix Enum error
        'notes' => "Input via WhatsApp ($period) - Sender: $phone",
    ]);
    ```

### 3. Execution

1.  **Apply Patch:** Modify the PHP file.
2.  **Deploy:** Copy file to production.
3.  **Test:** Retry the command `/suhu ruang gc 15.4`.

---

**Ready to execute?** Type "Execute" to apply the fix.
