# Plan: Fix Notification Timestamp Display

> **Status:** Ready for Execution
> **Issue:** WhatsApp notification timestamp shows "Sent 1 week ago" even after resending today.
> **Cause:** The view uses `created_at` which reflects the _first_ time the notification was created. Since we use `updateOrCreate`, the record is updated (resend), but `created_at` remains the same.
> **Fix:** Change the view to use `updated_at` which reflects the latest modification (resend time).

---

### 1. Implementation

**File:** `resources/views/delivery/show.blade.php`

**Change:**

```php
// BEFORE
<span class="text-xs text-gray-400">{{ $lastNotification->created_at->diffForHumans() }}</span>

// AFTER
<span class="text-xs text-gray-400">{{ $lastNotification->updated_at->diffForHumans() }}</span>
```

### 2. Deployment

1.  **Apply Patch:** Update local file.
2.  **Deploy:** SCP file to production.
3.  **Clear Cache:** `php artisan view:clear` on production.

---

**Ready to execute?** Type "Execute" to apply the fix.
