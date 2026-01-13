---
stepsCompleted:
    - step-01-init
    - step-02-discovery
    - step-03-success
    - step-04-journeys
    - step-05-domain
    - step-06-innovation
    - step-07-project-type
inputDocuments:
    - docs/index.md
    - docs/plans/2026-01-13-whatsapp-webhook-design.md
documentCounts:
    briefCount: 0
    researchCount: 0
    brainstormingCount: 0
    projectDocsCount: 2
workflowType: prd
classification:
    projectType: web_app
    domain: forensic-testing-lab
    complexity: high
    projectContext: brownfield
---

# Product Requirements Document - website-

**Author:** Lpmf-dev
**Date:** 2026-01-13

## Success Criteria

### User Success

- **Experience**: Users operate a **"Precision HUD"** interface (Futuristic yet Clinical) with high contrast and monospaced data.
- **Outcome**: Task completion error rate drops to **< 5%** (excluding user typos).
- **Adoption**: Users prefer the new UI over the legacy system (NPS increase > 15 points).
- **Reliability**: Users encounter **zero** critical data loss incidents.

### Business Success

- **Efficiency**: Reduce form-filling time by **30%** via streamlined "Precision HUD" workflows.
- **Accuracy**: **Zero** critical data loss incidents (P0 errors).
- **Brand**: Establish a "Modern Forensic" identity that inspires trust and authority ("Clinical Precision").

### Technical Success

- **Architecture**: **Hybrid Approach** - Next.js UI workspace for high-impact Dashboards/Entry; Blade/Alpine for Admin.
- **Reliability**:
    - **Circuit Breakers** implemented for WhatsApp integration.
    - **Optimistic Locking** for database status transitions.
    - **Global Error Handling** (100% async coverage).
- **Performance**: LCP < 1.5s, FID < 100ms on new Next.js pages.
- **Compliance**: 100% WCAG 2.1 AA compliance (High Contrast).

### Measurable Outcomes

- **Error Rate**: < 5% total system errors; 0% P0 critical errors.
- **Time on Task**: "Track Sample" < 30s; "Submit Request" < 3 mins.
- **WhatsApp Reliability**: > 99.9% uptime with circuit breaker protection.

## Product Scope

### MVP - Minimum Viable Product

- **Design System**: Implement **"Precision HUD"** theme in `packages/ui` (rename legacy design system naming).
- **Core Features**:
    - Rebuild **Dashboard** and **Request Entry** in Next.js.
    - Implement **Circuit Breaker** for WhatsApp Webhook.
    - Implement **Optimistic Locking** for Status Updates.
- **Validation**: User testing with 5 investigators to confirm <5% error rate.

### Growth Features (Post-MVP)

- **Mobile App**: Dedicated mobile app reusing `packages/ui` components.
- **Offline Mode**: PWA capabilities for field investigators.
- **Full Migration**: Migrate remaining Blade admin pages to Next.js.

### Vision (Future)

- **AI Integration**: Automated evidence analysis and pattern recognition.
- **Voice Command**: "Star Trek" style voice control for hands-free lab work.

## User Journeys

- **Journey 1: Detective Dedi — Field Status Check (Mobile, success path)**
  Opening: 2 AM in the rain, Dedi opens the app with gloves on. Rising: the **Precision HUD** loads in high‑contrast dark mode; large tap targets and a “Signal: Weak” banner appear. Climax: he searches the request ID; the HUD shows **MATCH CONFIRMED** with a subtle amber halo; status changes stream in once connectivity stabilizes. Resolution: he acts immediately, confident in the data. Requirements: offline queue + sync indicator, 48px touch targets, high contrast mode, job status polling, minimal animations.

- **Journey 2: Tech Tina — Batch Entry + Error Recovery (Desktop, edge case)**
  Opening: Monday backlog, 50 samples to enter. Rising: she uses keyboard‑first batch entry with “Focus Tunnel” highlighting the active field. Climax: she types `pH 15`; the field flags a **P1 validation warning** while the form auto‑saves every 30s. A brief Wi‑Fi glitch triggers “Draft saved — syncing.” Resolution: she reloads, recovers the draft, submits once, and avoids duplicate entries via idempotency keys. Requirements: auto‑save + draft recovery, inline validation severity levels, batch upload API with progress, keyboard shortcuts, idempotency.

- **Journey 3: Admin Andy — Notification Recovery (Ops)**
  Opening: WhatsApp outage at 2 AM. Rising: the health dashboard shows **Circuit Breaker: OPEN**, queue depth rising, and a retry timer. Climax: the provider returns; Andy clicks **Resume**, and the system drains the queue at a controlled rate. Resolution: backlog clears without duplicates; he exports a health report for leadership. Requirements: circuit breaker, queue dashboard, rate limiting, retry policy, auto‑refresh metrics, role‑based controls.

- **Journey 4: Support Sarah — Proof of Delivery (Support)**
  Opening: an investigator claims “no notification.” Rising: Sarah opens a unified timeline: report generated → notification sent → delivery status. Climax: one‑click resend + exportable audit trail with hash signature; she attaches proof to the ticket. Resolution: trust restored and the case closes in minutes, not hours. Requirements: unified audit log (ActivityLog + Outbox + Document), delivery status tracking, exportable audit PDF/CSV, resend controls.

### Journey Requirements Summary

- Offline queue + sync status UI for mobile usage.
- Auto‑save + recovery + idempotency for long/batch forms.
- Validation severity tiers (P0/P1/P2) and inline feedback.
- Admin observability: queue health, circuit breaker state, retry controls.
- Unified audit trail with export and proof of delivery.
- Accessibility: 48px touch targets, high‑contrast mode, minimal animation.

## Domain-Specific Requirements

### Compliance & Regulatory

- **ISO 17025:2017** compliance for forensic testing quality and documentation.
- **Pro‑justitia evidence handling** with court‑admissible chain‑of‑custody.
- **Audit logs must be immutable** and tamper‑evident (hash‑chain).
- **Retention**: minimum **3 years**, with audit‑safe export before purge.

### Technical Constraints

- **Sequential numbering** with **no gaps**; pattern locked after first issue.
- **Label freshness**: labels always reflect latest data; reprint requires justification + print count.
- **Access tiers** with **segregation of duties** for critical actions.
- **Uptime**: **99%** availability for core workflows.

### Integration & Scope

- No external integrations (no national ID, instruments, legal systems).
- Scope includes request → testing → report → handover admin flow, statistics, temperature monitoring, inventory, WhatsApp webhook.

### Risk Mitigations

- **Numbering gaps** → DB locking + gap detection + pattern immutability.
- **Label mismatch** → latest‑data print + reprint justification + snapshot logging.
- **Audit tampering** → DB immutability + hash chain.
- **Unauthorized edits** → RBAC + segregation of duties.
- **Retention violations** → minimum 1095 days + archive/purge policy.

## Innovation & Novel Patterns

### Detected Innovation Areas

- **Unified Ops Console (Phase 1)**: A single pane combining health checks, alerts, queue status, monitoring, and inventory signals into one operational view (read‑only aggregation).
- **Contextual Command Center (Phase 2)**: A task‑centric workspace embedded in `requests/show` with a precision‑style HUD overlay (high‑contrast data, SLA countdown, inline audit preview) to reduce cognitive load.
- **Immutable Audit Chain (Compliance Differentiator)**: Hash‑chained `audit_logs` for tamper‑evident chain‑of‑custody evidence.

### Market Context & Competitive Landscape

- Many lab systems offer dashboards, but few **unify ops telemetry + evidence workflows + immutable audit integrity** in a single surface.
- Differentiation is not “more dashboards,” but **reduced switching cost + verified audit integrity** within the operational flow.

### Validation Approach

- **Ops Console**: Measure MTTR reduction (time to diagnose incidents), alert‑to‑action time, and operator satisfaction.
- **Command Center**: A/B test task time and error rate vs legacy page; target <5% error rate and <30s sample status lookup.
- **Audit Chain**: Run quarterly audit drills with verification endpoint; demonstrate chain integrity without DB‑level trust.

### Risk Mitigation

- Keep Ops Console **read‑only** to limit operational risk.
- Maintain **online‑only numbering + labeling** to avoid custody gaps; offline is **metadata‑only** (no numbering/QR).
- Use feature flags to roll out Command Center gradually.

## Web Application Requirements

### Project-Type Overview

A **Hybrid Web Application** tailored for a private local network (WiFi) deployment. It combines a robust Laravel Admin panel (MPA) with a high-performance Next.js Command Center (SPA).

### Technical Architecture Considerations

#### Browser Support Matrix

- **Target**: Modern Browsers Only (Chrome, Edge, Firefox - Latest).
- **Legacy Support**: None.
- **Resolution**: Optimized for Desktop (1366x768+).

#### Responsive Design Strategy

- **Primary**: Desktop-First layout.
- **Mobile**: Functional fallback with "Best viewed on Desktop" advisory banner.
- **Field Access**: Primary mobile workflow delegated to **WhatsApp integration**.

#### Performance Targets (Low Risk)

- **Core Web Vitals**: LCP < 2.5s, FID < 100ms.
- **Latency**: API < 500ms; Polling updates < 200ms processing.

#### Accessibility Level

- **Standard**: WCAG 2.1 AA (High Contrast focus).
- **Navigation**: Keyboard-first for high-volume entry.

### Implementation Considerations

- **Real-Time Updates**: **Smart Polling** (30s interval) for dashboard status.
- **Security**: Local network deployment (HTTP allowed for MVP, HTTPS ready).
- **Deployment**: Local WiFi network context.
