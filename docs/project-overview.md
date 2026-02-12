# LPMF LIMS - Project Overview

> **Laboratory Information Management System untuk Laboratorium Pengujian Mutu Farmasi**

## Executive Summary

LPMF LIMS is a comprehensive Laboratory Information Management System designed for the Pharmaceutical Quality Testing Laboratory (Laboratorium Pengujian Mutu Farmasi). The system manages the complete lifecycle of pharmaceutical sample testing, from request submission through analysis to result delivery.

**Current Version:** v2.4.0 (12 Februari 2026)

## Project Classification

| Attribute | Value |
|-----------|-------|
| **Type** | Web Application (Single Laravel Monolith) |
| **Domain** | Healthcare / Laboratory Management |
| **Primary Language** | PHP 8.2+ |
| **Framework** | Laravel 12 |
| **Frontend** | Alpine.js 3.x + Tailwind CSS 3.x |
| **Database** | SQLite (Development) / PostgreSQL (Production) |
| **Testing** | Pest (Unit) + Laravel Dusk (E2E) |

## Technology Stack

| Category | Technology | Version | Purpose |
|----------|------------|---------|---------|
| **Backend** | Laravel | 12.x | MVC Framework |
| **Frontend JS** | Alpine.js | 3.x | Reactive UI Components |
| **CSS** | Tailwind CSS | 3.x | Utility-first Styling |
| **PDF Generation** | DomPDF | 3.1 | Document Generation |
| **Excel Export** | Maatwebsite Excel | 3.1 | Report Exports |
| **Permissions** | Spatie Laravel Permission | 6.x | RBAC |
| **Monitoring** | Laravel Pulse | 1.4 | Performance Monitoring |
| **Error Tracking** | Sentry | 4.20 | Error Reporting |
| **QR Codes** | SimpleSoftwareIO QRCode | 4.2 | Label Generation |
| **Build Tool** | Vite | 7.x | Asset Bundling |
| **Testing (Unit)** | Pest | Latest | PHP Testing |
| **Testing (E2E)** | Laravel Dusk | 8.x | Browser Testing |

## Core Business Domains

### 1. Request Management
- Test request submission and tracking
- Investigator (Penyidik) management
- Sample registration and documentation
- Berita Acara (official minutes) generation

### 2. Sample Processing
- Sample review (Kaji Ulang Permintaan)
- Testing workflow (Pengujian)
- Instrument logging and usage tracking
- Lab report generation (LHU)

### 3. Delivery Management
- Ready-for-delivery notifications
- Handover documentation
- Customer survey collection
- Pickup confirmation

### 4. Communication Hub (WhatsApp Integration)
- Automated notifications via GOWA API
- Staff task management
- Broadcast messaging
- Automated reminders (temperature, ISO countdown)
- Admin whitelist for command access

### 5. Monitoring & Quality
- Environment monitoring (temperature, humidity)
- Instrument usage logging
- IKU (Performance Index) tracking
- Consolidated reporting

### 6. Inventory Management
- Stock tracking for lab consumables
- Lot management
- Stock card generation
- Transaction history

## Architecture Pattern

The application follows a **Service-Oriented Layered Architecture**:

```
┌─────────────────────────────────────────────────────┐
│                   Blade Views                        │
│              (Alpine.js Components)                  │
├─────────────────────────────────────────────────────┤
│                   Controllers                        │
│          (Request Handling, Response)                │
├─────────────────────────────────────────────────────┤
│                    Services                          │
│         (Business Logic, Domain Rules)               │
├─────────────────────────────────────────────────────┤
│              Models + Repositories                   │
│           (Data Access, Eloquent ORM)                │
├─────────────────────────────────────────────────────┤
│                   Database                           │
│              (SQLite / PostgreSQL)                   │
└─────────────────────────────────────────────────────┘
```

## Key Integrations

| Integration | Purpose | Protocol |
|-------------|---------|----------|
| **GOWA API** | WhatsApp messaging | REST API + Webhooks |
| **AWS S3** | File storage | S3-compatible API |
| **Sentry** | Error tracking | SDK |

## Codebase Statistics

| Metric | Count |
|--------|-------|
| PHP Files | 270 |
| Blade Templates | 164 |
| JavaScript Files | 10 |
| Database Migrations | 105 |
| Test Files | 98 |
| Models | 57 |
| Controllers | 34+ |
| Services | 25+ |

## Documentation Resources

| Document | Description |
|----------|-------------|
| [WALKTHROUGH.md](../WALKTHROUGH.md) | Comprehensive changelog & feature history |
| [AGENTS.md](../AGENTS.md) | Agent workflow rules and guidelines |
| [RAMS_UI_GUIDELINES.md](../RAMS_UI_GUIDELINES.md) | Accessibility & UI standards |
| [VERCEL_GUIDELINES.md](../VERCEL_GUIDELINES.md) | Design system principles |
| [report/README.md](../report/README.md) | Audit system documentation |
| [docs/ALPINE_JS_PATTERNS.md](./ALPINE_JS_PATTERNS.md) | Alpine.js coding patterns |

## Getting Started

```bash
# Install dependencies
composer install && npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve

# In another terminal, start Vite
npm run dev
```

## Testing

```bash
# Run all tests
npm run test

# PHP tests only
npm run test:php

# E2E tests only
npm run test:e2e

# Run with watch mode
npm run test:php:watch
```

## Quality Audits

```bash
# Critical audits (must pass before commit)
npm run audit:critical

# Full audit suite
npm run audit:all

# Fix code style
./vendor/bin/pint
```
