# LPMF LIMS - Project Overview

## Executive Summary

LPMF LIMS is a Laravel 12 monolithic web application for forensic pharmaceutical laboratory operations (government context). It covers end-to-end request intake, sample testing workflow, document generation, delivery tracking, inventory management, WhatsApp automation, and quality/monitoring features.

## Project Classification

| Attribute | Value |
| --- | --- |
| Repository Type | Monolith |
| Project Type ID | web |
| Primary Runtime | PHP 8.2+ / Laravel 12 |
| Frontend Pattern | Blade + Alpine.js + Tailwind CSS |
| Database | PostgreSQL (default `pgsql`) |
| Queue/Async | Laravel Queue + job status tracking |

## Technology Stack

| Category | Technology | Version / Notes |
| --- | --- | --- |
| Backend Framework | Laravel | `^12.0` |
| Language | PHP | `^8.2` |
| Frontend | Blade, Alpine.js | `alpinejs ^3.4.2` |
| Styling | Tailwind CSS | `^3.4.18` |
| Build Tool | Vite | `^7.0.4` |
| Database | PostgreSQL | default driver in `config/database.php` |
| Authorization | spatie/laravel-permission | `^6.21` |
| PDF/Docs | barryvdh/laravel-dompdf | `^3.1` |
| E2E Testing | Laravel Dusk | `^8.3` |
| Unit/Feature Testing | Pest + PHPUnit | Pest + PHPUnit 11 |

## Current Codebase Metrics (Exhaustive Scan)

- Files scanned (source + config + docs): 5,229
- Total lines scanned: 2,296,478
- PHP files: 1,001
- Blade templates: 205
- Models: 64
- Controllers: 87
- Migrations: 116
- Test files: 154
- Routes (literal declarations): 359 (`api.php`: 123, `web.php`: 229)

## Core Domain Areas

1. Request intake and case registration (`TestRequest`, `Sample`, investigator flow)
2. Sample test lifecycle and process stages (`SampleTestProcess`, `TestResult`)
3. Controlled document generation and downloads (`Document`, templates, numbering)
4. Delivery and handover workflows (`Delivery`, `DeliveryItem`, survey)
5. Inventory and disposal management (`Inventory*`, `SampleDisposal`)
6. WhatsApp Hub operations (broadcasts, reminders, command handling)
7. Monitoring and reporting (environment, instruments, consolidated reporting, IKU)

## Architecture Snapshot

- Monolithic Laravel app with layered MVC + service-oriented business logic
- Blade server-rendered UI enhanced with Alpine.js stores/components
- API + web routes coexist in same deployable application
- Queue-enabled asynchronous jobs and webhook integrations
- Strong middleware usage for auth, verification, throttling, permissions, and audit

## Documentation Map

- [Architecture](./architecture.md)
- [Source Tree Analysis](./source-tree-analysis.md)
- [API Contracts](./api-contracts.md)
- [Data Models](./data-models.md)
- [Component Inventory](./component-inventory.md)
- [Development Guide](./development-guide.md)
- [Deployment Guide](./deployment-guide.md)
