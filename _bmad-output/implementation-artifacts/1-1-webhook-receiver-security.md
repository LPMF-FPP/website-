# Story 1.1: Webhook Receiver & Security

Status: in-progress

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a System,
I want to receive and verify WhatsApp webhooks,
so that only valid requests are processed.

## Acceptance Criteria

1. Route `POST /api/whatsapp/webhook` exists and accepts JSON payload.
2. Middleware checks `X-Hub-Signature-256` header against `WHATSAPP_WEBHOOK_SECRET` environment variable using HMAC-SHA256.
3. If signature is invalid or missing, return `403 Forbidden` response immediately.
4. If signature is valid, log the raw payload to `whatsapp_command_logs` table (status: `received`).
5. Returns `200 OK` response ({"status": "ok"}) for valid requests to acknowledge receipt.
6. Endpoint is throttled (rate limited) to prevent abuse.

## Tasks / Subtasks

- [ ] Webhook Route & Controller Skeleton (AC: 1, 5)
    - [ ] Define route in `routes/api.php`
    - [ ] Create `WhatsappWebhookController` with `handle` method
    - [ ] Implement `200 OK` response
- [ ] Security Verification (HMAC) (AC: 2, 3)
    - [ ] Implement HMAC-SHA256 verification logic
    - [ ] Verify `X-Hub-Signature-256` header
    - [ ] Handle missing/invalid signature with 403
- [ ] Logging (AC: 4)
    - [ ] Log incoming payload to `whatsapp_command_logs`
    - [ ] Extract sender number and message body
- [ ] Throttling (AC: 6)
    - [ ] Apply `throttle:api` or custom throttle middleware

## Dev Notes

- **Architecture Compliance**:
    - Controller: `app/Http/Controllers/Api/WhatsappWebhookController.php`
    - Model: `App\Models\WhatsappCommandLog`
    - Route: `routes/api.php` using API middleware group
- **Security**:
    - Secret key: `WHATSAPP_WEBHOOK_SECRET` (in `.env`)
    - Signature format: `sha256=<signature>`
- **Testing**:
    - `tests/Feature/WhatsappWebhookTest.php`
    - Must mock `WHATSAPP_WEBHOOK_SECRET` in tests
    - Test cases: valid signature, invalid signature, missing signature, valid payload logging

### Project Structure Notes

- Use standard Laravel 12 API controller patterns
- Use `App\Models` namespace for models
- Logging should use `whatsapp_command_logs` table which already exists (check migration `2026_01_11_163142_create_whatsapp_command_logs_table.php`)

### References

- [Design Doc: docs/plans/2026-01-13-whatsapp-webhook-design.md#1-webhook-receiver-whatsappwebhookcontroller]
- [Migration: database/migrations/2026_01_11_163142_create_whatsapp_command_logs_table.php]

## Dev Agent Record

### Agent Model Used

Google Antigravity (Sisyphus)

### Completion Notes List

- Design document successfully analyzed and integrated
- Existing table `whatsapp_command_logs` identified for logging
- Security requirements (HMAC) explicitly defined

### File List

- app/Http/Controllers/Api/WhatsappWebhookController.php
- routes/api.php
- tests/Feature/WhatsappWebhookTest.php
