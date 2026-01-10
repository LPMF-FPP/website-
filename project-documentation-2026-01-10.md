# LPMF LIMS - Project Documentation

**Laboratory Information Management System**  
**Documentation Generated**: January 10, 2026  
**Generated via**: BMAD Method `document-project` workflow adapted for OpenCode/Laravel  
**Party Mode Session**: 4 parallel agents (Frontend, Business Logic, Database, QA)

---

## Executive Summary

**LPMF LIMS** (Laboratorium Pengujian Mutu Farmasi - Pharmaceutical Quality Testing Laboratory) is a comprehensive Laboratory Information Management System built with **Laravel 12** and **PHP 8.3+**, designed to manage the complete sample testing lifecycle from submission through delivery with full traceability, quality assurance, and ISO 17025/QMS compliance features.

### Key Capabilities

- **Sample Lifecycle Management**: End-to-end workflow from request submission → testing → analysis → delivery
- **Multi-Stage Testing Workflow**: Administration, preparation, instrumentation, interpretation with status gates
- **Chain of Custody**: QR-coded evidence tracking with remaining sample management
- **Quality Monitoring**: Environment monitoring, instrument logging, weighing logs
- **Automated Notifications**: WhatsApp integration for milestone updates
- **Document Generation**: PDF reports (Berita Acara, Laporan Hasil Uji, monthly logs)
- **Compliance**: ISO 17025/QMS audit trail, activity logging, correction workflows
- **Inventory Management**: Reagent and consumable tracking with lot control
- **Multi-Tenant Design**: Support for external investigators and internal staff

---

## Technology Stack

### Backend

| Component          | Technology                | Version                  |
| ------------------ | ------------------------- | ------------------------ |
| **Framework**      | Laravel                   | 12.0                     |
| **Language**       | PHP                       | 8.2+                     |
| **Database**       | PostgreSQL                | (with pg_trgm extension) |
| **Queue**          | Laravel Queue             | (Redis-backed)           |
| **PDF Generation** | DomPDF                    | 3.1                      |
| **Excel**          | Maatwebsite Excel         | 3.1                      |
| **QR Codes**       | Simple QR Code            | 4.2                      |
| **Permissions**    | Spatie Laravel Permission | 6.21                     |
| **Storage**        | AWS S3 / Local            | (configurable)           |

### Frontend

| Component         | Technology   | Version      |
| ----------------- | ------------ | ------------ |
| **Templating**    | Blade        | (Laravel 12) |
| **JavaScript**    | Alpine.js    | 3.4.2        |
| **CSS Framework** | Tailwind CSS | 3.4.18       |
| **Build Tool**    | Vite         | 7.0.4        |
| **Design System** | dokpol-style | (monorepo)   |

### Quality Assurance

| Component         | Technology           | Purpose                        |
| ----------------- | -------------------- | ------------------------------ |
| **Testing**       | Pest PHP             | Feature & unit tests           |
| **CSS Linting**   | Stylelint            | CSS quality enforcement        |
| **JS Linting**    | ESLint               | JavaScript quality enforcement |
| **Accessibility** | axe-core + Puppeteer | WCAG AA compliance             |
| **Performance**   | Lighthouse CI        | Performance/SEO/best practices |
| **CSS Guard**     | Custom (Node.js)     | Overlay layout enforcement     |

### Development Tools

- **Node.js**: 20+
- **Composer**: PHP dependency management
- **npm**: JavaScript dependency management
- **Puppeteer**: Browser automation for audits

---

## Project Structure

```
lpmf-lims/
├── app/                          # Laravel application core
│   ├── Console/Commands/         # Artisan commands (backup, numbering, cleanup)
│   ├── Enums/                    # Business enums (status, roles, types)
│   ├── Events/                   # Domain events (NumberIssued, etc.)
│   ├── Http/
│   │   ├── Controllers/          # MVC controllers
│   │   │   ├── Api/              # API endpoints
│   │   │   ├── Reports/          # Report generation
│   │   │   └── *.php             # Web controllers
│   │   └── Middleware/           # Request middleware (AuditActivity, etc.)
│   ├── Jobs/                     # Queue jobs (WhatsApp, backup)
│   ├── Listeners/                # Event listeners
│   ├── Models/                   # Eloquent models (43 models)
│   ├── Observers/                # Model observers (audit logging)
│   ├── Policies/                 # Authorization policies
│   ├── Repositories/             # Repository pattern
│   ├── Services/                 # Business logic services
│   │   ├── WhatsApp/             # WhatsApp integration
│   │   ├── Settings/             # Settings management
│   │   └── *.php                 # Domain services
│   ├── Support/                  # Support utilities (ActivityLogger, helpers)
│   └── helpers.php               # Global helper functions
│
├── database/
│   ├── migrations/               # 74 database migrations
│   ├── factories/                # Model factories
│   └── seeders/                  # Database seeders
│
├── resources/
│   ├── css/                      # Application stylesheets
│   ├── js/                       # Alpine.js modules
│   │   ├── pages/                # Page-specific modules
│   │   └── utils/                # Shared utilities
│   └── views/                    # Blade templates
│       ├── auth/                 # Authentication views
│       ├── components/           # Reusable Blade components
│       ├── delivery/             # Delivery management
│       ├── inventory/            # Inventory management
│       ├── monitoring/           # Environment/instrument monitoring
│       ├── pdf/                  # PDF templates
│       ├── requests/             # Test request management
│       ├── sample-processes/     # Sample testing workflow
│       ├── settings/             # System settings UI
│       └── tracking/             # Public tracking
│
├── routes/
│   ├── web.php                   # Web routes
│   ├── api.php                   # API routes
│   ├── auth.php                  # Authentication routes
│   └── console.php               # Console routes
│
├── scripts/audit/                # Quality assurance scripts
│   ├── axe-scan.mjs              # Accessibility audit
│   ├── css-cascade.mjs           # CSS cascade analysis
│   ├── css-coverage.mjs          # CSS coverage audit
│   ├── color-contrast.mjs        # Color contrast audit
│   ├── guard-nonlayout.mjs       # Layout property guard (CRITICAL)
│   ├── zindex-map.mjs            # Z-index topology
│   └── run-audit.sh              # Interactive audit runner
│
├── report/                       # Audit outputs
│   ├── README.md                 # Audit system documentation
│   └── *.md / *.json             # Audit reports
│
├── styles/                       # Design system CSS
│   ├── base.css                  # Base styles
│   ├── components.css            # Component styles
│   ├── tokens.css                # Design tokens
│   ├── pd-*.css                  # Safe overlay CSS (strict rules)
│   └── a11y.css                  # Accessibility utilities
│
├── dokpol-style/                 # Custom design system monorepo
│   ├── apps/web/                 # Next.js showcase
│   └── packages/                 # Shared UI packages
│
├── tests/                        # Test suite
│   ├── Feature/                  # Feature tests
│   └── Unit/                     # Unit tests
│
├── config/                       # Laravel configuration
├── public/                       # Public assets
├── storage/                      # File storage
├── AGENTS.md                     # AI agent workflow documentation
├── WALKTHROUGH.md                # Project history and PRD
├── todos.md                      # Active task tracking
├── composer.json                 # PHP dependencies
├── package.json                  # JS dependencies
├── tailwind.config.js            # Tailwind configuration
├── vite.config.js                # Vite build configuration
└── lighthouserc.json             # Lighthouse CI configuration
```

---

## Core Business Entities

### Entity Relationship Overview

```
investigators (external clients)
    ↓ creates
test_requests (case header)
    ↓ contains
samples (specimens)
    ↓ goes through
sample_test_processes (stages: preparation → instrumentation → interpretation)
    ↓ produces
test_results (analytical findings)
    ↓ generates
documents (PDF reports)
    ↓ packaged into
deliveries → completion
```

### Key Entities

#### 1. **Investigators**

- **Purpose**: External clients (POLRI, external applicants) who submit test requests
- **Key Fields**: `name`, `agency`, `folder_key` (unique storage path), `email`, `phone`
- **Relationships**: Has many `test_requests`, `documents` (uploads)

#### 2. **Test Requests**

- **Purpose**: Central business entity representing a testing case
- **Key Fields**:
    - `request_number` (auto-generated, unique)
    - `receipt_number` (auto-generated, unique)
    - `status` (submitted → verified → received → in_testing → ready_for_delivery → completed)
    - `investigator_id`, `user_id` (creator)
- **Relationships**:
    - Belongs to `investigators`, `users`
    - Has many `samples`, `documents`, `suspects`, `deliveries`, `instrument_usage_logs`
    - Has one `customer_surveys`, `evidence_units` (via samples)

#### 3. **Samples**

- **Purpose**: Individual specimens within a test request
- **Key Fields**:
    - `sample_code` (auto-generated, unique)
    - `sample_form` (padat, cair, semi_padat)
    - `sample_category` (narkotika, obat, bahan_kimia)
    - `sample_status` (prep_pending → prep_done → instrument_done → interpretation_done → ready_for_delivery)
    - `test_methods` (JSON: uv_vis, gc_ms, lc_ms)
    - Testing metadata: `quantity`, `physical_identification`, `assigned_analyst_id`
    - Weighing: `generic_weight_grams`, `uvvis_weighed_grams` (if UV-VIS method)
- **Relationships**:
    - Belongs to `test_requests`
    - Has many `sample_test_processes`, `test_results`, `instrument_usage_logs`
    - Has one `evidence_units` (1:1, unique `sample_id`)

#### 4. **Sample Test Processes**

- **Purpose**: Track per-sample workflow stages with metadata
- **Stages** (enum):
    - `administration` - Initial admin review
    - `preparation` - Sample preparation
    - `instrumentation` - Instrument testing
    - `interpretation` - Results interpretation
- **Key Fields**: `sample_id`, `stage` (unique combo), `performed_by`, `started_at`, `completed_at`, `metadata` (JSON)
- **Business Rules**:
    - Unique constraint: `(sample_id, stage)`
    - Sequential: Must complete prior stages before starting next
    - Interpretation generates LHU number (auto-assigned once)

#### 5. **Documents**

- **Purpose**: File attachments (uploaded or generated)
- **Types** (CHECK constraint):
    - Uploaded: `request_letter`, `evidence_photo`, `investigator_uploads`
    - Generated: `berita_acara_penerimaan`, `laporan_hasil_uji`, `ba_penyerahan`, `form_preparation`, monthly logs, etc.
- **Key Fields**: `document_type`, `path`, `file_size`, `source` (upload|generated), `metadata`, `investigator_id`, `test_request_id`
- **Relationships**: Polymorphic attachment to investigators/test requests

#### 6. **Deliveries**

- **Purpose**: Package completed test results for handover
- **Key Fields**: `request_id`, `status` (pending → ready → completed), `delivered_by`, `delivered_at`
- **Relationships**: Has many `delivery_items` (documents)

#### 7. **Evidence Units & Remaining Units**

- **Purpose**: Chain of custody tracking with QR codes
- **Evidence Units**: Original sample unit (1:1 with sample, unique `sample_id`, `qr_token`)
- **Remaining Units**: Leftover evidence after testing (`remaining_code`, unique `qr_token`)

---

## Database Architecture

### Schema Statistics

- **Total Migrations**: 74
- **Total Models**: 43
- **Database**: PostgreSQL with pg_trgm extension for full-text search

### Core Tables

| Table                   | Purpose                                  | Key Relationships                                     |
| ----------------------- | ---------------------------------------- | ----------------------------------------------------- |
| `users`                 | Staff (admin, analyst, supervisor, etc.) | → test_requests, samples, documents, deliveries       |
| `investigators`         | External clients                         | → test_requests, documents                            |
| `test_requests`         | Case header                              | → samples, documents, deliveries, suspects            |
| `samples`               | Specimens                                | → sample_test_processes, test_results, evidence_units |
| `sample_test_processes` | Workflow stages                          | Belongs to samples                                    |
| `test_results`          | Analytical findings                      | Belongs to samples                                    |
| `documents`             | File attachments                         | Polymorphic to investigators/test_requests            |
| `deliveries`            | Handover packages                        | → test_requests, delivery_items                       |
| `evidence_units`        | Original samples (QR)                    | 1:1 with samples                                      |
| `remaining_units`       | Leftover evidence (QR)                   | → samples                                             |

### Supporting Tables

| Table              | Purpose                                  |
| ------------------ | ---------------------------------------- |
| `suspects`         | Suspected substances per request         |
| `customer_surveys` | Customer satisfaction (1:1 with request) |
| `survey_responses` | Survey ratings                           |
| `whatsapp_outbox`  | Queued WhatsApp messages                 |
| `activity_logs`    | User action audit trail                  |
| `audit_logs`       | Settings/template change logs            |

### Monitoring & Quality Tables

| Table                            | Purpose                                  |
| -------------------------------- | ---------------------------------------- |
| `environment_locations`          | Monitored areas (room, fridge, freezer)  |
| `environment_readings`           | Temp/humidity logs with correction trail |
| `instruments`                    | Instrument catalog                       |
| `instrument_assets`              | Physical instrument units                |
| `method_instrument_requirements` | Required instruments per test method     |
| `instrument_usage_logs`          | Per-sample instrument usage tracking     |

### Inventory Tables

| Table                 | Purpose                      |
| --------------------- | ---------------------------- |
| `inventory_items`     | Reagents/consumables catalog |
| `inventory_lots`      | Lot/batch tracking           |
| `inventory_locations` | Storage locations            |
| `inventory_balances`  | Current stock levels         |
| `inventory_movements` | Stock transactions           |

### System Tables

| Table                                        | Purpose                                  |
| -------------------------------------------- | ---------------------------------------- |
| `settings`                                   | System configuration (JSONB)             |
| `sequences`                                  | Numbering sequence tracking              |
| `document_templates`                         | Blade/GrapesJS templates with versioning |
| `jobs`, `job_batches`, `failed_jobs`         | Laravel queue                            |
| `backup_runs`                                | Backup automation logs                   |
| `recent_requests`                            | User recent request tracking             |
| `sessions`, `cache`, `password_reset_tokens` | Laravel internals                        |

### Data Flow: Sample Testing Lifecycle

```
1. REQUEST SUBMISSION
   investigators → test_requests (status: submitted)
                → samples (status: preparation_pending)
                → suspects
                → documents (request_letter, evidence_photo)
                → evidence_units (QR codes generated)

2. TEST DATA ENTRY (Pre-Testing)
   users → samples (assign analyst, methods, quantity)
        → sample_test_processes (create stages: prep, instrument, interpret)
        → test_requests (status: in_testing)

3. PREPARATION STAGE
   users → sample_test_processes (stage: preparation, metadata)
        → samples (generic_weight_grams, uvvis_weighed_grams if UV-VIS)
        → instrument_usage_logs (PREP instruments)
        → samples (status: preparation_done)

4. INSTRUMENTATION STAGE
   users → sample_test_processes (stage: instrumentation, metadata)
        → instrument_usage_logs (RUN instruments)
        → samples (status: instrumentation_done)

5. INTERPRETATION STAGE
   users → sample_test_processes (stage: interpretation, metadata)
        → test_results (detected substance, result)
        → documents (generate laporan_hasil_uji PDF with LHU number)
        → samples (status: interpretation_done)

6. READY FOR DELIVERY
   system → samples (status: ready_for_delivery)
          → test_requests (status: ready_for_delivery)
          → deliveries (create delivery record)

7. DELIVERY COMPLETION
   users → customer_surveys (complete survey)
        → deliveries (status: completed, delivered_by, delivered_at)
        → test_requests (status: completed)
        → remaining_units (create if evidence remains)
        → documents (generate ba_penyerahan PDF)

8. NOTIFICATIONS & AUDIT
   observers → whatsapp_outbox (queue milestone messages)
            → activity_logs (log all status transitions)
```

---

## Business Logic & Workflows

### Workflow Stages

Defined in `app/Enums/TestProcessStage.php`:

1. **Administration** - Initial admin review
2. **Preparation** - Sample preparation
3. **Instrumentation** - Instrument testing
4. **Interpretation** - Results interpretation and reporting

### Status Transitions

#### Test Request Status Flow

```
submitted → verified → received → in_testing → ready_for_delivery → completed
```

#### Sample Status Flow

```
preparation_pending →
preparation_done →
instrumentation_done →
interpretation_done →
ready_for_delivery
```

#### Delivery Status Flow

```
pending → ready → completed
```

### Key Business Rules

#### 1. **Numbering Rules**

- **Request Number**: Auto-generated on `TestRequest` creation via `NumberingService`
- **Receipt Number**: Auto-generated on `TestRequest` creation
- **Sample Code**: Auto-generated on `Sample` creation
- **LHU Number**: Auto-generated once during interpretation stage (reused on regeneration)

#### 2. **Stage Gate Validation** (`WorkflowService`)

- Sample must be in required status to start each stage
- **Preparation Gate**: If instrument logging enabled, weighing required
- **Instrumentation Gate**: All instrument usage logs must be complete
- **Interpretation Completion**: Creates delivery record automatically

#### 3. **Access Control** (Policies)

- **Investigator Documents**: Investigators can only view/upload their own documents
- **Admin/Analyst/Supervisor**: Broader access to all documents
- **Role-based**: `users.role` CHECK constraint (admin, analyst, supervisor, etc.)

#### 4. **WhatsApp Milestone Notifications**

Triggered via Observers (`TestRequestObserver`, `SampleObserver`):

| Milestone                  | Trigger Event                 | Template                                                    |
| -------------------------- | ----------------------------- | ----------------------------------------------------------- |
| REQUEST_RECEIVED           | TestRequest created           | "Permintaan Anda telah diterima. Resi: {resi}."             |
| REVIEW_DONE_READY_FOR_TEST | Sample → ADMIN_DONE           | "Permintaan {resi} telah selesai dikaji ulang..."           |
| PREPARATION_DONE           | Sample → PREPARATION_DONE     | "Permintaan {resi} telah selesai dipreparasi sampel."       |
| INSTRUMENTATION_DONE       | Sample → INSTRUMENTATION_DONE | "Permintaan {resi} telah selesai diuji instrumen."          |
| INTERPRETATION_DONE        | Sample → INTERPRETATION_DONE  | "Permintaan {resi} telah selesai dilakukan interpretasi..." |
| READY_FOR_PICKUP           | Sample → READY_FOR_DELIVERY   | "Permintaan {resi} siap diambil."                           |
| HANDOVER_COMPLETED         | TestRequest → completed       | "Permintaan {resi} telah diambil dan serah terima dicatat." |

#### 5. **Delivery Completion Gate**

- All samples must be `ready_for_delivery`
- Customer survey must be completed
- Only then can delivery be marked `completed`

#### 6. **Environment Monitoring Windows**

- Morning window: 07:00-09:00 (closes after 09:00)
- Afternoon window: 13:00-15:00 (remains open)
- Validation: Temperature required, humidity optional (per config)
- Corrections: Insert new record with `correction_of_id` pointing to original (audit trail)

#### 7. **Instrument Logging**

- Required instruments defined in `method_instrument_requirements` (per test method)
- Usage types: PREP (preparation), RUN (instrumentation)
- Validation: All mandatory instruments must have valid asset selection
- Blocking: Cannot finalize stage without complete instrument logs

---

## Service Layer Architecture

### Core Services

| Service                        | Responsibility                 | Key Methods                                                                         |
| ------------------------------ | ------------------------------ | ----------------------------------------------------------------------------------- |
| `WorkflowService`              | Stage gates, delivery creation | `canStartStage()`, `completeTestProcess()`, `markReadyForDelivery()`                |
| `NumberingService`             | Auto-numbering                 | `generateRequestNumber()`, `generateReceiptNumber()`, `generateSampleCode()`        |
| `DocumentService`              | Document storage/retrieval     | `storeGenerated()`, `storeStandaloneReport()`                                       |
| `SettingsRepository`           | System settings CRUD           | `get()`, `set()`, `all()`                                                           |
| `EnvironmentMonitoringService` | Monitoring rules               | `getDueListForUser()`, `validateMetrics()`, `createReading()`, `createCorrection()` |
| `InstrumentLoggingService`     | Instrument logging             | `requirementsForMethod()`, `getAvailableAssets()`, `createUsageLogs()`              |
| `BackupService`                | Backup automation              | `createBackup()`, `purgeOldBackups()`                                               |

### WhatsApp Integration Services

| Service                       | Responsibility                                  |
| ----------------------------- | ----------------------------------------------- |
| `NotificationService`         | Milestone templates, enablement logic           |
| `GowaClient`                  | HTTP client for go-whatsapp-web-multidevice API |
| `SendWhatsAppNotificationJob` | Queued message delivery with retry              |

### PDF Generation Services

| Service                      | Responsibility             |
| ---------------------------- | -------------------------- |
| `PdfRenderService`           | DomPDF wrapper             |
| `MonthlyLogReportController` | Monthly log PDF generation |

---

## Frontend Architecture

### UI Framework Stack

- **Blade**: Server-side templating with layouts and components
- **Alpine.js 3.4.2**: Reactive state management (JavaScript framework)
- **Tailwind CSS 3.4.18**: Utility-first CSS framework
- **Vite 7.0.4**: Frontend build tool

### Layout Structure

```
resources/views/layouts/
├── app.blade.php           # Main authenticated layout
├── guest.blade.php         # Guest/auth layout
└── navigation.blade.php    # Primary navigation with dropdowns
```

### Blade Component Catalog

Located in `resources/views/components/`:

**Navigation Components:**

- `nav-link.blade.php` - Active/inactive navigation link
- `responsive-nav-link.blade.php` - Mobile navigation link
- `dropdown.blade.php` - Dropdown menu
- `breadcrumbs.blade.php` - Breadcrumb navigation

**Form Components:**

- `text-input.blade.php` - Text input control
- `input-label.blade.php` - Form label
- `input-error.blade.php` - Error message display

**Button Components:**

- `button.blade.php` - Base button with variants
- `primary-button.blade.php` - Primary action button
- `secondary-button.blade.php` - Secondary action button
- `danger-button.blade.php` - Destructive action button

**UI Components:**

- `modal.blade.php` - Reusable modal with focus trapping
- `card.blade.php` - Card wrapper
- `kpi-card.blade.php` - KPI dashboard card
- `status-badge.blade.php` - Status indicator badge
- `alert.blade.php` - Alert notification
- `tabs.blade.php` - Tab navigation
- `empty-state.blade.php` - Empty state UI
- `skeleton-table.blade.php` - Loading skeleton
- `icon.blade.php` - Icon component with ARIA support

### Alpine.js Modules

**Global Modules** (registered in `resources/js/app.js`):

- `listFetcher` - Pagination/filtering for lists
- `dashboardStats` - Dashboard stats polling
- `settingsPageAlpine` - Settings section management

**Page-Specific Modules:**

| Module                      | Location                                    | Purpose                           |
| --------------------------- | ------------------------------------------- | --------------------------------- |
| `bladeTemplateEditor`       | settings/blade-templates.blade.php          | Template editing, preview, backup |
| `requestDocuments`          | requests/show.blade.php                     | Document management for requests  |
| `environmentMonitoring`     | monitoring/environment/index.blade.php      | Environment reading input         |
| `locationManager`           | monitoring/environment/manage.blade.php     | Location CRUD management          |
| `correctionForm`            | monitoring/environment/correction.blade.php | Reading correction workflow       |
| `instrumentLogging`         | sample-processes/edit.blade.php             | Instrument selection              |
| `analyticalBalanceWeighing` | sample-processes/edit.blade.php             | Weighing data entry               |
| `trackingProgress`          | tracking/result.blade.php                   | Public tracking with polling      |
| `handoverCard`              | delivery/show.blade.php                     | Delivery PDF status check         |
| `remainingLabelApp`         | partials/remaining-label-section.blade.php  | Label CRUD                        |

**Standalone Modules** (non-Alpine):

- `resources/js/pages/search.js` - Advanced search with debounce
- `resources/js/pages/requests-form.js` - Dynamic form toggles

### Design System & CSS Architecture

**CSS Structure:**

```
styles/
├── base.css                    # Token-driven base styles
├── components.css              # Component-level CSS
├── tokens.css                  # Design tokens
├── pd.components.css           # Overlay-safe components
├── pd-safe-layers.css          # Safe overlay layers
├── pd.ultrasafe.tokens.css     # Tokenized overlay set
├── pd.framework-bridge.css     # Bridge layer
└── a11y.css                    # Accessibility utilities
```

**CSS Rules Enforcement:**

- **pd-\*.css files**: CANNOT use layout properties (enforced by `audit:guard`)
- **Audit Failure**: Build fails if overlay CSS violates layout rules
- **Purpose**: Ensures CSS overlays are "safe" and non-breaking

**External Design System:**

- `dokpol-style/` - Next.js monorepo with shared UI packages
- Purpose: Centralized design token management

### Accessibility Features

**ARIA & Semantic HTML:**

- Skip-to-content link in main layout
- Focus trapping in modals (`x-modal`)
- `aria-live` for dynamic updates (tracking, skeletons)
- `aria-current` on navigation links
- `role="dialog"`, `aria-modal="true"` on modals
- `role="alert"` on notifications
- `aria-label` on breadcrumbs, tabs, buttons

**Keyboard Support:**

- Modal escape key handling
- Dropdown keyboard navigation
- Focus management on modal open/close

**Visual Accessibility:**

- `x-cloak` to prevent Alpine initialization flash
- Color contrast enforcement via `audit:contrast`
- Semantic HTML structure throughout

---

## Quality Assurance System

### Audit Types & Tools

| Audit Type           | Script                | Purpose                                         | Exit on Failure   |
| -------------------- | --------------------- | ----------------------------------------------- | ----------------- |
| **CSS Guard**        | `guard-nonlayout.mjs` | Enforce overlay CSS rules (pd-\*.css no layout) | ✅ Yes (CRITICAL) |
| **CSS Cascade**      | `css-cascade.mjs`     | Specificity analysis, conflict detection        | ❌ No             |
| **Color Contrast**   | `color-contrast.mjs`  | WCAG AA contrast + theme parity                 | ❌ No             |
| **Z-Index Topology** | `zindex-map.mjs`      | Z-index conflict detection                      | ❌ No             |
| **Accessibility**    | `axe-scan.mjs`        | axe-core + Puppeteer a11y audit                 | ❌ No             |
| **CSS Coverage**     | `css-coverage.mjs`    | Unused CSS detection via Puppeteer              | ❌ No             |
| **Lighthouse CI**    | `lhci autorun`        | Performance, SEO, best practices                | ❌ No             |
| **CSS Linting**      | Stylelint             | CSS quality enforcement                         | ❌ No             |
| **JS Linting**       | ESLint                | JavaScript quality enforcement                  | ❌ No             |

### NPM Script Organization

```json
{
    "audit:critical": "guard + cascade + contrast (CI, pre-commit)",
    "audit:all": "Full audit suite (stylelint + eslint + all audits)",
    "audit:stylelint": "CSS linting",
    "audit:eslint": "JS linting",
    "audit:a11y": "Accessibility audit",
    "audit:coverage": "CSS coverage",
    "audit:cascade": "CSS cascade analysis",
    "audit:guard": "Layout property guard (CRITICAL)",
    "audit:contrast": "Color contrast",
    "audit:zindex": "Z-index topology"
}
```

### Quality Gates

**Pre-Commit** (documented, not auto-enabled):

```bash
npm run audit:guard
```

**CI/CD** (recommended):

```bash
npm run audit:critical && npm run test && npm run build
```

**Definition of Done** (per AGENTS.md):

- [ ] All tests pass: `npm run test`
- [ ] Critical audits pass: `npm run audit:critical`
- [ ] Code linted: `npx eslint ... --fix` && `npx stylelint ... --fix`
- [ ] WALKTHROUGH.md updated
- [ ] `/changelogs` page updated
- [ ] `todos.md` items marked `[x]`
- [ ] Git commit with descriptive message

### Test Coverage

**PHP Tests** (Pest):

- **Feature Tests**: 50+ tests covering auth, settings, numbering, documents, PDF generation, queue config, localization, tracking, API endpoints
- **Unit Tests**: Repository and service layer tests

**JavaScript Tests** (Node test runner):

- `tests/js/search.test.js` - Search functionality
- `tests/js/settings-whatsapp.test.js` - WhatsApp settings

### Monitoring & Logging

**Activity Logging:**

- `ActivityLogger` - Central audit/activity logging to DB
- `AuditActivity` middleware - Records audit logs for requests
- Observers - Document, Sample, TestRequest lifecycle logging
- Schema: `activity_logs` table with before/after JSON

**Environment Monitoring:**

- `environment_locations` - Monitored areas (room, fridge, freezer)
- `environment_readings` - Temperature/humidity logs with windows
- Corrections: Immutable audit trail (new record + `correction_of_id`)

**Instrument Logging:**

- `instrument_usage_logs` - Per-sample instrument tracking
- Monthly aggregations for reporting

**System Logging:**

- Laravel logging channels: `config/logging.php`
- Storage: `storage/logs/laravel.log`

### ISO 17025 / QMS Compliance

**Compliance Features:**

- **Audit Trail**: Activity logs, immutable corrections, document logging
- **Traceability**: QR-coded evidence, instrument logs, environment monitoring
- **Data Integrity**: Correction workflows, before/after logging
- **Monthly Reports**: PDF logs for environment, instruments, weighing

**UI References:**

- `monitoring-logging.blade.php` - ISO 17025/QMS references
- `monitoring/environment/index.blade.php` - ISO 17025 compliance messaging
- `landing.blade.php` - ISO 17025 compliance messaging

---

## Integration Points

### External Services

| Integration  | Technology                      | Purpose                         |
| ------------ | ------------------------------- | ------------------------------- |
| **WhatsApp** | go-whatsapp-web-multidevice API | Milestone notifications         |
| **AWS S3**   | Laravel Flysystem               | Document storage (configurable) |
| **Email**    | Laravel Mail                    | Notifications, issue alerts     |
| **PDF**      | DomPDF                          | Document generation             |
| **Excel**    | Maatwebsite Excel               | Data exports                    |
| **QR Codes** | Simple QR Code                  | Evidence tracking               |

### WhatsApp Integration Architecture

```
TestRequest/Sample Status Change
    ↓ (Observer)
WhatsAppOutbox Record Created
    ↓ (Queue)
SendWhatsAppNotificationJob
    ↓
GowaClient → HTTP POST to go-whatsapp-web-multidevice API
    ↓
WhatsAppOutbox Updated (status: sent/failed/delivered/read)
```

**Configuration:**

- Base URL: Configurable via settings (`notifications.whatsapp.base_url`)
- Milestones: Enabled/disabled per milestone in settings
- Templates: Customizable per milestone with `{resi}` placeholder
- Queue: Automatic retry on failure (max 5 attempts, exponential backoff)

### Document Storage Flow

```
Document Generation Request
    ↓
DomPDF Renders Blade Template
    ↓
DocumentService::storeGenerated()
    ↓
Store to filesystem (local or S3)
    ↓
Create Document record in DB
    ↓
Return Document instance
```

---

## Automation & Background Jobs

### Queue Jobs

| Job                           | Purpose                | Trigger                   |
| ----------------------------- | ---------------------- | ------------------------- |
| `SendWhatsAppNotificationJob` | Send WhatsApp messages | Observer (status changes) |
| `EmergencyBackupJob`          | Database backup        | Manual/scheduled          |

### Console Commands

| Command                              | Purpose                       |
| ------------------------------------ | ----------------------------- |
| `BackupCleanupCommand`               | Purge old backups             |
| `SyncNumberingSequences`             | Sync numbering sequences      |
| `FixNumberingSequence`               | Fix numbering sequence issues |
| `CleanupOrphanedInvestigatorFolders` | Remove orphaned folders       |
| `CleanupDuplicateDocuments`          | Remove duplicate documents    |
| `PurgeOldFiles`                      | Purge old files from storage  |
| `QueueHealthCheck`                   | Check queue health            |
| `SyncDocumentTemplates`              | Sync document templates       |
| `GenerateBeritaAcara`                | Generate Berita Acara PDF     |
| `CreateAdminUser`                    | Create admin user             |

### Observers

| Observer              | Triggers                 | Actions                                  |
| --------------------- | ------------------------ | ---------------------------------------- |
| `TestRequestObserver` | Request lifecycle events | Activity logging, WhatsApp notifications |
| `SampleObserver`      | Sample status changes    | Activity logging, WhatsApp notifications |
| `DocumentObserver`    | Document lifecycle       | Activity logging                         |

### Event Listeners

| Event          | Listener                | Action                                 |
| -------------- | ----------------------- | -------------------------------------- |
| `NumberIssued` | `SendIssueNotification` | Send email/WhatsApp on number issuance |

---

## Configuration

### Key Configuration Files

| File                     | Purpose                            |
| ------------------------ | ---------------------------------- |
| `config/app.php`         | Application configuration          |
| `config/database.php`    | Database connections               |
| `config/queue.php`       | Queue configuration                |
| `config/logging.php`     | Logging channels                   |
| `config/dompdf.php`      | PDF generation settings            |
| `config/filesystems.php` | Storage configuration              |
| `config/services.php`    | Third-party services               |
| `.env`                   | Environment-specific configuration |

### Environment Variables (`.env`)

**Critical Variables:**

- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION` (Redis recommended)
- `FILESYSTEM_DISK` (local or s3)
- `AWS_*` (if using S3)
- `AUDIT_URLS` (for a11y/coverage audits)
- `WHATSAPP_BASE_URL`, `WHATSAPP_API_KEY`

---

## Development Workflow

### Setup Commands

```bash
# Install dependencies
composer install && npm install

# Run migrations
php artisan migrate

# Generate application key
php artisan key:generate

# Build frontend
npm run build

# Development mode (multi-service)
composer dev
# Runs: php artisan serve + queue:listen + pail + npm run dev
```

### Development Server

```bash
# Single server
php artisan serve

# Multi-service (recommended)
composer dev
```

### Testing

```bash
# PHP tests
php artisan test
# or
composer test

# JavaScript tests
npm run test:search
```

### Quality Assurance

```bash
# Run critical audits
npm run audit:critical

# Run all audits
npm run audit:all

# Run specific audit
npm run audit:guard
npm run audit:a11y
```

### Pre-PR Checklist

```bash
npm run audit:critical && npm run test && npm run build
```

---

## Security Considerations

### Authentication & Authorization

- **Authentication**: Laravel Breeze (session-based)
- **Authorization**: Spatie Laravel Permission + custom policies
- **Password Reset**: Token-based via email
- **Session Management**: Database sessions

### Access Control

**User Roles** (CHECK constraint):

- `admin` - Full system access
- `analyst` - Testing and analysis
- `supervisor` - Oversight and approval
- `investigator` - External client (limited access)

**Policies:**

- `InvestigatorPolicy` - Investigator data access
- `InvestigatorDocumentPolicy` - Document view/upload/delete by role

### Data Protection

- **Password Hashing**: bcrypt
- **CSRF Protection**: Laravel middleware
- **SQL Injection**: Eloquent ORM parameterized queries
- **XSS Protection**: Blade template escaping
- **File Upload Validation**: MIME type, size limits

### Audit Trail

- **Activity Logging**: All critical actions logged to `activity_logs`
- **Immutable Corrections**: Environment reading corrections preserve original
- **Document Versioning**: Template versioning with change logs
- **Queue Message Tracking**: WhatsApp outbox with delivery status

---

## Performance Optimization

### Database Optimization

- **Indexes**: Performance indexes on frequently queried columns
- **Full-Text Search**: pg_trgm extension for fuzzy search
- **Query Optimization**: Eager loading to prevent N+1 queries
- **Connection Pooling**: Configured via `config/database.php`

### Caching Strategy

- **Cache Driver**: Redis (recommended for production)
- **Session Cache**: Database or Redis
- **Config/Route Caching**: Artisan commands for production

### Frontend Performance

- **Vite Build**: Optimized production builds
- **CSS Purging**: Tailwind purges unused CSS
- **Asset Versioning**: Vite cache busting
- **Lazy Loading**: Alpine.js components load on demand

### Queue Management

- **Queue Driver**: Redis (recommended)
- **Queue Workers**: `queue:listen` or `queue:work`
- **Failed Jobs**: Tracked in `failed_jobs` table
- **Retry Logic**: Exponential backoff for WhatsApp

---

## Deployment Considerations

### Production Requirements

- **PHP**: 8.2+
- **Node.js**: 20+
- **PostgreSQL**: Latest stable
- **Redis**: Latest stable (for queue/cache)
- **Web Server**: Nginx or Apache
- **Process Manager**: Supervisor (for queue workers)

### Build Steps

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader
npm ci --production

# Build frontend
npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
```

### Queue Workers (Supervisor)

```ini
[program:lpmf-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

### Backup Strategy

- **Database**: `EmergencyBackupJob` with automatic cleanup
- **Files**: S3 or local filesystem with rotation
- **Frequency**: Configurable (daily recommended)
- **Retention**: Configurable (30 days recommended)

---

## Troubleshooting

### Common Issues

**1. Audit Failures**

```bash
# CSS guard violations
npm run audit:guard
# Fix: Remove layout properties from pd-*.css files

# Accessibility issues
npm run audit:a11y
# Fix: Add ARIA attributes, improve semantic HTML
```

**2. Laravel Server Not Running**

```bash
# A11y and coverage audits require running server
php artisan serve

# Then run audits
npm run audit:a11y
npm run audit:coverage
```

**3. Puppeteer Chrome Download**

```bash
# Puppeteer auto-downloads Chromium
npm install puppeteer

# If issues, see report/README.md for troubleshooting
```

**4. Queue Jobs Not Processing**

```bash
# Check queue connection
php artisan queue:failed

# Restart queue workers
supervisorctl restart lpmf-queue-worker:*
```

**5. WhatsApp Notifications Not Sending**

- Check `whatsapp_outbox` table for failed messages
- Verify `notifications.whatsapp.base_url` setting
- Ensure go-whatsapp-web-multidevice service is running
- Check queue workers are processing

---

## Additional Documentation

### Internal Documentation

- **WALKTHROUGH.md** - Complete project history, PRD, and changelog
- **AGENTS.md** - AI agent workflow and collaboration protocols
- **todos.md** - Active task tracking
- **report/README.md** - Audit system comprehensive guide
- **dokpol-style/README.md** - Design system documentation

### External References

- **Laravel 12 Documentation**: https://laravel.com/docs/12.x
- **Alpine.js Documentation**: https://alpinejs.dev
- **Tailwind CSS Documentation**: https://tailwindcss.com/docs
- **Pest PHP Documentation**: https://pestphp.com
- **axe-core Documentation**: https://github.com/dequelabs/axe-core

---

## Appendix

### Model Catalog (43 Models)

**Core Domain:**

- TestRequest, Sample, SampleTestProcess, TestResult, Document, Delivery, DeliveryItem

**Users & Investigators:**

- User, Investigator

**Evidence Tracking:**

- EvidenceUnit, RemainingUnit, LabelPrintLog

**Surveys:**

- CustomerSurvey, SurveyResponse

**Suspects & Cases:**

- Suspect, Case, Person (case_people pivot)

**Monitoring & Quality:**

- EnvironmentLocation, EnvironmentReading, Instrument, InstrumentAsset, MethodInstrumentRequirement, InstrumentUsageLog

**Inventory:**

- InventoryItem, InventoryLot, InventoryLocation, InventoryBalance, InventoryMovement

**Settings & Templates:**

- Setting (system_settings table), Sequence, DocumentTemplate, AuditLog

**Messaging:**

- WhatsappOutbox

**System:**

- BackupRun, RecentRequest, JobStatus, ActivityLog

### Controller Catalog

**Web Controllers:**

- RequestController, SampleTestController, ProcessController, SampleTestProcessController
- DeliveryController, TrackingController, DashboardController, SettingsController
- EnvironmentMonitoringController, InstrumentLoggingController
- InventoryController, SearchController, ChangellogController

**API Controllers:**

- DocumentsController, GroupedSearchController, DashboardStatsController
- Settings/\* (multiple settings API controllers)

**Report Controllers:**

- MonthlyLogReportController

### Service Catalog

**Core Services:**

- WorkflowService, NumberingService, DocumentService, BackupService
- EnvironmentMonitoringService, InstrumentLoggingService

**Settings Services:**

- SettingsRepository, SettingsResponseBuilder

**WhatsApp Services:**

- NotificationService, GowaClient, WhatsAppService

**PDF Services:**

- PdfRenderService

**Template Services:**

- DocumentTemplateActivation, DocumentTemplateService

---

## Metadata

**Documentation Date**: January 10, 2026  
**Generated By**: Party Mode (4 parallel explore agents)  
**Session Duration**: ~4 minutes (parallel execution)  
**Agent Findings**:

- Frontend Architecture: 2m 34s
- Business Logic & Workflows: 3m 5s
- Quality Assurance System: 3m 13s
- Database Architecture: 4m 9s

**Total Coverage**:

- 74 database migrations analyzed
- 43 Eloquent models documented
- 50+ Blade components cataloged
- 15+ Alpine.js modules mapped
- 7 audit types detailed
- 50+ PHP tests inventoried

---

**End of Documentation**

This comprehensive documentation provides a complete overview of the LPMF LIMS project architecture, business logic, quality assurance, and deployment considerations. For ongoing updates, refer to WALKTHROUGH.md and follow the brownfield development workflow documented in AGENTS.md.
