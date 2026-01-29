# Plan: Fix WhatsApp Notification Unique Constraint Violation

> **Status:** Ready for Execution
> **Issue:** `UniqueConstraintViolationException` when sending "READY_FOR_PICKUP" notification because a record with the same `test_request_id` and `milestone_key` already exists.
> **Fix:** Use `updateOrCreate` instead of `create` to handle existing records gracefully (updating them triggers a resend).

---

### 1. Analysis

- **Error:** `SQLSTATE[23505]: Unique violation` on `whatsapp_outbox`.
- **Location:** `App\Http\Controllers\DeliveryController::sendPickupNotification` (Line 420).
- **Cause:** The code uses `Model::create()` which fails if the notification entry already exists.
- **Solution:** Switch to `Model::updateOrCreate()`. This will:
    1.  Find the existing record (if any).
    2.  Update it with new details (phone, message).
    3.  Reset status to `queued` (allowing the worker to pick it up again).
    4.  Reset attempts to `0`.

### 2. Implementation

**File:** `app/Http/Controllers/DeliveryController.php`

**Change:**

```php
// BEFORE
$outbox = \App\Models\WhatsappOutbox::create([
    'test_request_id' => $request->id,
    'milestone_key' => 'READY_FOR_PICKUP',
    'to_phone_e164' => \App\Support\PhoneNormalizer::toE164($phone),
    // ...
]);

// AFTER
$outbox = \App\Models\WhatsappOutbox::updateOrCreate(
    [
        'test_request_id' => $request->id,
        'milestone_key' => 'READY_FOR_PICKUP',
    ],
    [
        'to_phone_e164' => \App\Support\PhoneNormalizer::toE164($phone),
        'to_jid' => $jid,
        'message_text' => $message,
        'status' => 'queued', // Reset status to allow resend
        'attempts' => 0,      // Reset attempts
        'last_error' => null, // Clear previous errors
    ]
);
```

### 3. Verification

- **Test:** Trigger the notification for a request that already has a record.
- **Expected Result:** No 500 error; the existing record is updated to `queued` and processed.

---

**Ready to execute?** Type "Execute" to apply the fix.
