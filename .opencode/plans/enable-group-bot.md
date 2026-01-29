# Plan: Enable WhatsApp Group Bot & Reminders

> **Status:** Proposal (Read-Only)
> **Goal:** Allow the WhatsApp bot to function in groups, specifically for sending system reminders (Stock/Expiry alerts) to a group instead of a specific person.

## 1. Analysis

- **Current State:**
    - **Sending:** `InventoryAlertService` forces `@s.whatsapp.net` suffix, limiting reminders to individual numbers.
    - **Receiving:** The bot listens to webhooks, but we don't have a way to know the **Group ID** (JID) to configure it as the recipient.
- **Requirement:**
    1.  We need a way to discover the Group ID.
    2.  We need to configure the system to send alerts to that Group ID.

## 2. Implementation Plan

### A. New Command: `/id` (or `/info`)

Create a new command that simply replies with the sender's JID.

- **Usage:** User adds bot to group, types `/id`.
- **Response:** "ID Chat Ini: 123456789-999@g.us".
- **Purpose:** Allows admin to copy the Group ID.

### B. Update `InventoryAlertService`

Modify the `sendNotification` method in `app/Services/Inventory/InventoryAlertService.php`.

- **Logic:** Check if the configured `admin_number` already contains `@` (e.g., ends in `@g.us`).
- **Change:**

    ```php
    // Existing
    $this->whatsapp->sendMessage($adminNumber.'@s.whatsapp.net', $message);

    // Proposed
    $jid = str_contains($adminNumber, '@') ? $adminNumber : $adminNumber.'@s.whatsapp.net';
    $this->whatsapp->sendMessage($jid, $message);
    ```

### C. Configuration

- **Action:** User updates the `notifications.whatsapp.admin_number` setting (in DB or `.env`) with the Group ID obtained from step A.

## 3. Execution Steps

1.  **Create** `app/Services/WhatsApp/Commands/GetIdCommand.php`.
2.  **Register** command in `app/Services/WhatsApp/CommandDispatcher.php`.
3.  **Modify** `app/Services/Inventory/InventoryAlertService.php` to handle Group JIDs.

---

**Ready to execute?** Type "Execute" to apply the changes.
