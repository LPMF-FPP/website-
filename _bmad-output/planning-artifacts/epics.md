# Epic 1: WhatsApp Webhook & Command System

## Context

Integrate WhatsApp webhook to allow stock updates and temperature monitoring via chat commands.

## Requirements

- Secure webhook endpoint (HMAC)
- Parse "STOK" and "SUHU" commands
- Update Inventory and Environment tables
- Log all interactions

### Story 1.1: Webhook Receiver & Security

**As a** System
**I want to** receive and verify WhatsApp webhooks
**So that** only valid requests are processed

**Acceptance Criteria:**

- Route `POST /api/whatsapp/webhook` exists
- Middleware checks `X-Hub-Signature-256`
- Returns 403 if signature invalid
- Returns 200 OK if valid

### Story 1.2: Inventory Command Logic

**As a** Staff Member
**I want to** update stock via WhatsApp
**So that** I don't need to open the laptop

**Acceptance Criteria:**

- Pattern: `STOK [CODE] [QTY]`
- Positive Qty = RECEIPT, Negative = ISSUE
- Updates `inventory_movements` and `inventory_balances`
- Returns success/error message

### Story 1.3: Environment Command Logic

**As a** Lab Technician
**I want to** report temperature via WhatsApp
**So that** monitoring is real-time

**Acceptance Criteria:**

- Pattern: `SUHU [LOCATION] [TEMP]`
- Optional humidity support
- Validates location code
- Records to `environment_readings`

### Story 1.4: Response & Logging

**As a** Admin
**I want to** see logs of all commands
**So that** I can audit usage

**Acceptance Criteria:**

- Log raw payload to `whatsapp_command_logs`
- Reply to user with result (Success/Error)
