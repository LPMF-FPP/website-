# Story 1.1: Webhook Receiver & Security

Status: completed

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

- [x] Webhook Route & Controller Skeleton (AC: 1, 5)
    - [x] Define route in `routes/api.php`
    - [x] Create `WhatsappWebhookController` with `handle` method
    - [x] Implement `200 OK` response
- [x] Security Verification (HMAC) (AC: 2, 3)
    - [x] Implement HMAC-SHA256 verification logic
    - [x] Verify `X-Hub-Signature-256` header
    - [x] Handle missing/invalid signature with 403
- [x] Logging (AC: 4)
    - [x] Log incoming payload to `whatsapp_command_logs`
    - [x] Extract sender number and message body
- [x] Throttling (AC: 6)
    - [x] Apply `throttle:60,1` middleware

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
- All 4 acceptance criteria tests passing (2026-01-13)
- Fixed double-encoding bug (params column) - Model casts to array, removed json_encode
- Fixed payload parsing for both test (raw JSON) and production (application/json) formats

### File List

- app/Http/Controllers/Api/WhatsappWebhookController.php
- routes/api.php
- tests/Feature/WhatsappWebhookTest.php
