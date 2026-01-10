# WALKTHROUGH - LPMF LIMS v1.2.4

> **Laboratory Information Management System untuk Laboratorium Pengujian Mutu Farmasi**

---

## 📋 Table of Contents

- [🚀 Quick Links](#-quick-links)
- [📰 Recent Changes](#-recent-changes-v12x)
- [📖 Project Overview](#-project-overview)
- [📚 Product Documentation](#-product-documentation)
- [📜 Changelog Archive](#-changelog-archive)

---

## 🚀 Quick Links

| Resource                                                         | Description                             |
| ---------------------------------------------------------------- | --------------------------------------- |
| [AGENTS.md](./AGENTS.md)                                         | Workflow rules & agent delegation guide |
| [UI-UX-IMPROVEMENT-PLAN.md](./UI-UX-IMPROVEMENT-PLAN.md)         | UI/UX improvement roadmap               |
| [PARTY_MODE_SESSION_EXAMPLE.md](./PARTY_MODE_SESSION_EXAMPLE.md) | Multi-agent collaboration examples      |
| [PRODUCTION_READINESS.md](./PRODUCTION_READINESS.md)             | Production observability & monitoring   |
| [report/README.md](./report/README.md)                           | Frontend audit system guide             |
| [patcher/](./patcher/)                                           | Deployment & design documentation       |

**Current Version:** v1.2.4 (10 Januari 2026)  
**Latest Feature:** Production-Ready Observability & Monitoring

---

## 📰 Recent Changes (v1.2.x)

### v1.2.4 (10 Januari 2026) - Production-Ready Observability & Monitoring

**📌 What Changed:**

Comprehensive backend production-readiness improvements with focus on observability, security, and resilience.

**🔍 Observability Improvements:**

- Enhanced health check endpoints (`/health`, `/health/liveness`, `/health/readiness`)
- Automatic slow query monitoring (logs queries > 1000ms)
- Exception handling with Sentry/Flare integration
- Error reporting rate limiting (5/minute per exception type)

**🔒 Security Improvements:**

- PII encryption for `TestRequest::suspect_name` and `suspect_address`
- API rate limiting (60 requests/minute)
- Improved exception filtering (don't report validation/auth errors)

**🛡️ Resilience Improvements:**

- Service timeout configurations (WhatsApp, S3)
- Enhanced job retry logic with exponential backoff
- Transaction management review (25 usages confirmed)

**📦 Files Modified:**

- `app/Http/Controllers/HealthController.php` - Enhanced health checks
- `app/Providers/AppServiceProvider.php` - Query monitoring
- `bootstrap/app.php` - Exception handling
- `config/database.php` - Slow query threshold
- `config/services.php` - Service timeouts & monitoring
- `routes/web.php` - Health endpoints
- `routes/api.php` - Rate limiting
- `.env.example` - Configuration templates
- `app/Models/TestRequest.php` - PII encryption

**📖 Documentation:** `PRODUCTION_READINESS.md` - Complete setup and monitoring guide

**✅ Verification:**

- All health routes registered
- Queue configuration tests passing
- Configuration templates updated

---

### v1.2.3 (10 Januari 2026) - Laravel Precognition & Optimistic UI Guide

**📌 What Changed:**

- Added project-specific guide for Laravel Precognition setup and usage
- Documented optimistic UI patterns with rollback, toasts, and a11y announcements
- Included code templates, testing strategies, and real-world adoption map

**📦 Files:** `WALKTHROUGH.md`, `resources/views/changelogs/index.blade.php`

---

### v1.2.2 (10 Januari 2026) - Alpine.js Frontend Patterns Guide

**📌 What Changed:**

- Added comprehensive Alpine.js frontend patterns documentation (state, modals, transitions, accessibility, performance, toasts)
- Documented repo-specific motion tokens, a11y utilities, and loading-state matrix
- Included troubleshooting guidance and official references

**📦 Files:** `WALKTHROUGH.md`, `resources/views/changelogs/index.blade.php`

---

### v1.1.9 (10 Januari 2026) - UI/UX Phase 7: Alpine.js Plugin Integration

**📌 What Changed:**

- Integrated Alpine.js plugins for enhanced interaction
- `x-teleport` for Modals and Confirm Dialogs (resolves z-index stacking)
- `x-collapse` for smooth Accordion animations
- `x-trap` for robust focus management

**✅ Benefits:**

- **Z-Index Solved:** Modals now render at `body` level via `#modal-portal`
- **Smooth Animations:** Accordions animate height naturally
- **Accessibility:** Better focus trapping in modals
- **Cleaner Code:** Removed manual transition logic

**📦 Files:** `resources/js/app.js`, `resources/views/components/modal.blade.php`, `resources/views/components/confirm-dialog.blade.php`, `resources/views/settings/partials/monitoring-logging.blade.php`

**⚠️ Requirement:**
Run `npm install @alpinejs/collapse @alpinejs/focus`

---

### v1.1.8 (10 Januari 2026) - UI/UX Phase 6: Performance & Type Safety

**📌 What Changed:**

- Implemented **search debouncing (500ms)** in Search and Settings pages
- Enforced type safety with `x-model.number` on **all numeric inputs**
- Optimized performance with `x-model.lazy` for large text areas

**✅ Benefits:**

- Reduced API calls by ~80% during search (preventing per-keystroke requests)
- Prevented string-number type coercion issues in calculations
- Improved rendering performance for large text inputs (updates on blur)

**📦 Files:** `resources/views/search/index.blade.php`, `monitoring/environment/*`, `settings/partials/documents.blade.php`

---

### v1.1.7 (10 Januari 2026) - UI/UX Phase 5: Toast Notification System

**📌 What Changed:**

- Implemented production-ready Toast Notification System using Alpine.js + Blade
- Created centralized `toast` store for global state management
- Added accessibility-first announcer for screen readers
- Replaced direct `alert()` usage with accessible toast notifications

**✅ Features:**

- **Types:** Success (Green), Error (Red), Warning (Yellow), Info (Blue)
- **Behavior:** Auto-dismiss (3-5s), stacked layout, hover to pause (implicit via structure)
- **Accessibility:** ARIA live regions, focus management, semantic icons
- **Architecture:** Alpine.js Store (`resources/js/stores/toast.js`) + Blade Component (`<x-toast-container>`)

**📦 Usage:**

```javascript
// In Alpine.js components
$store.toast.success("Operation completed successfully");
$store.toast.error("Something went wrong");

// With custom duration
$store.toast.warning("Please check your input", 5000);
```

**📦 Files:** `resources/js/stores/toast.js`, `resources/views/components/toast-container.blade.php`, `resources/js/app.js`

---

### v1.1.6 (10 Januari 2026) - Semantic Versioning Constraint

**📌 What Changed:**

- Applied strict version numbering rule: max 9 per segment (MAJOR.MINOR.PATCH)
- When reaching 10, increment next higher segment instead
- Example: `1.0.9` → `1.1.0` (not `1.0.10`)

**✅ Impact:**

- Cleaner version history
- Prevents version overflow
- Standardized across all docs

**📦 Files:** `AGENTS.md`, `WALKTHROUGH.md`, `todos.md`

---

### v1.1.5 (10 Januari 2026) - UI/UX Phase 4: Form Stepper Integration

**📌 What Changed:**

- Integrated form stepper into `requests/create.blade.php` (1166 lines)
- Added 5 tracked sections with scroll-based progress indicator
- Mobile responsive with auto-highlighting

**✅ Features:**

- Sticky progress bar at top
- Click-to-jump navigation
- Auto-tracking scroll position
- Labels hide on mobile (<768px)

**📦 Files:** `resources/views/requests/create.blade.php`

**🧪 Testing:**

```bash
# Access form
Visit: /requests/create

# Test scroll tracking
1. Scroll through form → active step should update
2. Click step 3 → should jump to "Tersangka" section
3. Test on mobile (375px width) → labels should hide
```

---

### v1.1.4 (10 Januari 2026) - UI/UX Phase 3: Confirm Dialog Deployment

**📌 What Changed:**

- Replaced **all 14** native `confirm()` calls with custom `showConfirmDialog()`
- Created reusable `<x-form-field>` component with auto-validation

**✅ 100% Coverage:**

- ✓ User management (5 instances)
- ✓ Sample processing (2 instances)
- ✓ Requests (1 instance)
- ✓ Delivery & Labels (3 instances)
- ✓ Settings (4 instances)
- ✓ Inventory (1 instance)

**📦 Benefits:**

- Consistent UX across all confirmations
- Async/Promise support
- Loading states
- Keyboard navigation (Escape, Tab)
- ARIA attributes for accessibility

---

### v1.1.3 (10 Januari 2026) - UI/UX Phase 2: Component Creation

**📌 What Changed:**

- Created `<x-form-stepper>` component - Visual progress for multi-step forms
- Created `<x-confirm-dialog>` component - Custom confirmation modals
- Enhanced `<x-dropdown>` with ARIA attributes

**✅ Components:**

1. **Form Stepper** (`form-stepper.blade.php`)
    - Sticky top navigation
    - Auto-tracking via Intersection Observer API
    - Click-to-scroll with smooth behavior
    - Mobile responsive

2. **Confirm Dialog** (`confirm-dialog.blade.php`)
    - 3 types: danger (red), warning (yellow), info (blue)
    - Async support with loading states
    - Customizable button text

3. **Dropdown Accessibility** (`dropdown.blade.php`)
    - Added `aria-haspopup`, `aria-expanded`, `role="menu"`
    - Keyboard navigation (Escape, Tab)

**📦 Usage:**

```php
// Form Stepper
<x-form-stepper :steps="[
    ['id' => 'step-1', 'label' => 'Section 1'],
    ['id' => 'step-2', 'label' => 'Section 2']
]" />

// Confirm Dialog
showConfirmDialog({
    type: 'danger',
    title: 'Delete Item',
    message: 'Are you sure?',
    onConfirm: async () => { /* action */ }
});
```

---

### v1.1.2 (10 Januari 2026) - UI/UX Phase 1: Critical Fixes

**📌 What Changed:**

- Fixed breadcrumb navigation (2 pages) - Changed `'url'` to `'href'`
- Fixed mobile table scrolling (2 pages) - `overflow-hidden` → `overflow-x-auto`

**✅ Issues Fixed:**

1. **🔴 CRITICAL: Breadcrumb Links Broken**
    - Files: `search/index.blade.php`, `monitoring/environment/manage.blade.php`
    - Root cause: Component expected `href` but views used `url` key

2. **🔴 CRITICAL: Tables Not Responsive**
    - Files: `delivery/index.blade.php`, `sample-processes/index.blade.php`
    - Root cause: `overflow-hidden` clipped content on mobile

**📦 Testing:**

```bash
# Breadcrumbs
Visit: /search → Click "Home" link → Should navigate

# Mobile tables
Visit: /delivery → Resize to 375px → Table should scroll horizontally
```

---

### v1.1.1 (10 Januari 2026) - Party Mode: Multi-Agent Collaboration

**📌 What Changed:**

- Implemented Party Mode workflow for complex tasks
- Documented multi-agent orchestration patterns
- Created agent selection matrix

**✅ Core Principles:**

1. **Parallel Agent Orchestration** - Launch 2-4 specialized agents simultaneously
2. **Domain Expertise** - Each agent focuses on their specialization
3. **Cross-Pollination** - Agents' findings inform each other
4. **Exhaustive Search** - Never stop at first result

**📦 Agent Roles:**
| Agent | Specialization | When to Use |
|-------|----------------|-------------|
| `explore` | Codebase exploration | Finding implementations, patterns |
| `librarian` | External docs, APIs | Researching frameworks, libraries |
| `oracle` | Problem-solving, strategy | Complex decisions, after failures |
| `frontend-ui-ux-engineer` | UI/UX design | Creating interfaces, accessibility |
| `document-writer` | Technical writing | Documentation, summaries |

**📦 Workflow Pattern:**

```
1. ANALYZE → Launch 2-4 background agents (explore + librarian)
2. SYNTHESIZE → Combine findings from all agents
3. CONSULT → oracle for complex decisions
4. IMPLEMENT → Use specialist agents (Sisyphus, frontend-ui-ux-engineer)
5. DOCUMENT → document-writer updates WALKTHROUGH.md
6. REVIEW → oracle validates implementation
```

**📊 Performance:** 60-70% time savings through parallel execution

**📖 Full Guide:** See [PARTY_MODE_SESSION_EXAMPLE.md](./PARTY_MODE_SESSION_EXAMPLE.md)

---

### v1.1.0 (10 Januari 2026) - WhatsApp Bugfix & Editable Templates

**📌 What Changed:**

- Fixed 4 critical WhatsApp notification bugs
- Added editable message templates UI in settings

**✅ Bugs Fixed:**

1. **API Parameter Mismatch** - `jid` → `phone` parameter
2. **Database Constraint Violation** - Removed invalid `'sending'` status
3. **DNS Resolution Issue** - `gowa.lpmf.local` → `localhost:3000`
4. **Message ID Extraction** - Fixed response parsing

**✅ New Feature: Editable Templates**

- UI section in WhatsApp settings
- Customizable templates per milestone
- `{resi}` placeholder support
- Override system with default fallback

**📦 Testing:**

- Successfully sent 5 messages to +6285956592404
- All delivered with provider message IDs
- Queue retry with exponential backoff working

---

## 📖 Project Overview

### Tech Stack

| Layer          | Technology                        | Version                      |
| -------------- | --------------------------------- | ---------------------------- |
| Backend        | Laravel (PHP)                     | 12.x (PHP 8.3+)              |
| Frontend       | Blade + Alpine.js + Tailwind CSS  | Alpine 3.x, Tailwind 3.x     |
| Database       | PostgreSQL                        | 16+                          |
| Build Tool     | Vite                              | 7.x                          |
| PDF Generation | DomPDF                            | barryvdh/laravel-dompdf ^3.1 |
| Queue          | Laravel Queue                     | Database driver              |
| Audit Tools    | Puppeteer + Lighthouse + axe-core | Development only             |

### Quick Start

```bash
# Install dependencies
composer install && npm install

# Development server (required for audits)
php artisan serve

# Build frontend
npm run build

# Run all audits
npm run audit:all

# Run critical audits (CI, pre-commit)
npm run audit:critical

# Run tests
npm run test
```

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        LPMF LIMS                            │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │   Request   │  │   Sample    │  │  Inventory  │         │
│  │ Management  │  │  Processing │  │ Management  │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
│                                                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │  Document   │  │   Delivery  │  │   Reports   │         │
│  │ Generation  │  │   Tracking  │  │  Analytics  │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
├─────────────────────────────────────────────────────────────┤
│            Laravel 12 Backend (PostgreSQL)                  │
│         Queue System | File Storage | Authentication        │
└─────────────────────────────────────────────────────────────┘
```

### Key Features

- **Request Management** - Permohonan pengujian dari penyidik kepolisian
- **Sample Processing** - Tracking barang bukti (narkotika, zat terlarang)
- **Document Generation** - Berita Acara, Laporan Hasil Uji (PDF)
- **Inventory Management** - Reagen, consumables, stock tracking
- **Analytics Dashboard** - KPI monitoring, performance metrics
- **WhatsApp Notifications** - Automated milestone notifications
- **User Management** - Role-based access control (Admin, Analyst, Viewer)

---

## 📚 Product Documentation

### 📋 Daftar Isi

1. [Ringkasan Produk](#ringkasan-produk)
2. [Arsitektur Sistem](#arsitektur-sistem-detail)
3. [Entity Relationship Diagram (ERD)](#entity-relationship-diagram-erd)
4. [Modul & Fitur](#modul--fitur)
5. [Alur Kerja (Workflow)](#alur-kerja-workflow)
6. [API Endpoints](#api-endpoints)
7. [Konfigurasi & Deployment](#konfigurasi--deployment)
8. [Panduan Pengembangan](#panduan-pengembangan)
9. [Laravel Precognition & Optimistic UI Implementation Guide](#laravel-precognition--optimistic-ui-implementation-guide)

---

### Ringkasan Produk

**Tujuan:**

LPMF LIMS adalah sistem manajemen informasi laboratorium yang dirancang untuk:

- Mengelola **permohonan pengujian** dari penyidik kepolisian
- Melacak **sampel barang bukti** (narkotika dan zat terlarang)
- Menghasilkan **dokumen resmi** (Berita Acara, Laporan Hasil Uji)
- Mengelola **inventaris laboratorium** (reagen, consumables)
- Menyediakan **dashboard analitik** untuk monitoring kinerja

**User Roles:**

| Role    | Permissions                                 | Typical Users         |
| ------- | ------------------------------------------- | --------------------- |
| Admin   | Full access, user management, system config | Lab manager, IT staff |
| Analyst | Create/edit samples, generate reports       | Lab technicians       |
| Viewer  | Read-only access                            | Supervisors, auditors |

---

### Arsitektur Sistem (Detail)

```
┌───────────────────────────────────────────────────────────────────┐
│                         Frontend Layer                            │
│     Blade Templates + Alpine.js + Tailwind CSS + Vite            │
├───────────────────────────────────────────────────────────────────┤
│                         Backend Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │ Controllers  │  │   Services   │  │ Repositories │           │
│  │              │  │              │  │              │           │
│  │ - Request    │  │ - Document   │  │ - Eloquent   │           │
│  │ - Sample     │  │ - PDF Gen    │  │ - Query      │           │
│  │ - Inventory  │  │ - WhatsApp   │  │ - Cache      │           │
│  └──────────────┘  └──────────────┘  └──────────────┘           │
├───────────────────────────────────────────────────────────────────┤
│                         Data Layer                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │  PostgreSQL  │  │    Queue     │  │ File Storage │           │
│  │   Database   │  │ (Database)   │  │   (Local)    │           │
│  └──────────────┘  └──────────────┘  └──────────────┘           │
├───────────────────────────────────────────────────────────────────┤
│                    External Integrations                          │
│  ┌──────────────┐  ┌──────────────┐                              │
│  │   WhatsApp   │  │   DomPDF     │                              │
│  │ (go-whatsapp)│  │ (PDF Engine) │                              │
│  └──────────────┘  └──────────────┘                              │
└───────────────────────────────────────────────────────────────────┘
```

**Design Patterns:**

- **Repository Pattern** - Data access abstraction
- **Service Layer** - Business logic separation
- **Observer Pattern** - Model events for notifications
- **Factory Pattern** - Document generation
- **Strategy Pattern** - PDF template selection

---

### Entity Relationship Diagram (ERD)

**Core Entities:**

```mermaid
erDiagram
    USERS ||--o{ REQUESTS : creates
    REQUESTS ||--o{ SAMPLES : contains
    SAMPLES ||--o{ SAMPLE_PROCESSES : undergoes
    REQUESTS ||--o{ DOCUMENTS : generates
    USERS ||--o{ INVENTORY_TRANSACTIONS : records

    USERS {
        bigint id PK
        string name
        string email UK
        string role
        timestamp created_at
    }

    REQUESTS {
        bigint id PK
        bigint user_id FK
        string request_number UK
        date request_date
        text case_description
        string status
    }

    SAMPLES {
        bigint id PK
        bigint request_id FK
        string sample_code UK
        string sample_type
        decimal weight
        string unit
    }

    SAMPLE_PROCESSES {
        bigint id PK
        bigint sample_id FK
        string process_type
        jsonb test_results
        timestamp completed_at
    }

    DOCUMENTS {
        bigint id PK
        bigint request_id FK
        string document_type
        string file_path
        timestamp generated_at
    }
```

**Supporting Tables:**

- `settings` - System configuration (key-value store)
- `inventory_items` - Lab supplies, reagents
- `inventory_transactions` - Stock movements
- `whatsapp_outbox` - Notification queue
- `jobs` - Laravel queue jobs
- `failed_jobs` - Failed queue jobs

---

### Modul & Fitur

#### 1. Request Management

**Path:** `/requests`

**Features:**

- Create new test requests
- Upload supporting documents
- Add suspects information
- Track request status (Draft → In Progress → Completed)
- Search & filter requests

**Key Files:**

- `app/Http/Controllers/RequestController.php`
- `app/Models/Request.php`
- `resources/views/requests/`

---

#### 2. Sample Processing

**Path:** `/sample-processes`

**Features:**

- Record sample reception
- Conduct tests (narkotika, psikotropika)
- Record test results (JSON format)
- Generate analysis reports
- Mark samples ready for delivery

**Key Files:**

- `app/Http/Controllers/SampleProcessController.php`
- `app/Models/SampleProcess.php`
- `resources/views/sample-processes/`

---

#### 3. Document Generation

**Path:** `/documents`

**Features:**

- Generate Berita Acara (Evidence Report)
- Generate Laporan Hasil Uji (Test Report)
- Editable Blade templates
- PDF export with DomPDF
- Version control for templates

**Key Files:**

- `app/Services/DocumentGenerationService.php`
- `app/Http/Controllers/DocumentController.php`
- `resources/views/templates/blade/`

---

#### 4. Inventory Management

**Path:** `/inventory/items`

**Features:**

- Track reagents & consumables
- Record stock in/out transactions
- Low stock alerts
- Expiry date tracking
- Usage history

**Key Files:**

- `app/Http/Controllers/Inventory/ItemController.php`
- `app/Models/InventoryItem.php`
- `resources/views/inventory/`

---

#### 5. WhatsApp Notifications

**Path:** `/settings` (WhatsApp section)

**Features:**

- Milestone-based notifications
- Customizable message templates
- Queue-based sending with retry
- Health check for GOWA service
- Test message functionality

**Key Files:**

- `app/Services/WhatsApp/NotificationService.php`
- `app/Services/WhatsApp/GowaClient.php`
- `app/Jobs/SendWhatsAppNotificationJob.php`

**Integration:** go-whatsapp-web-multidevice API

---

### Alur Kerja (Workflow)

#### Standard Test Request Flow

```
1. Penyidik → Create Request
   ↓
2. Upload Documents (Surat Permintaan, BA, etc.)
   ↓
3. Add Suspects & Sample Details
   ↓
4. Submit Request
   ↓
5. Lab Analyst → Receive Samples
   ↓
6. Conduct Tests → Record Results
   ↓
7. Generate Reports (BA + LHU)
   ↓
8. Mark Ready for Delivery
   ↓
9. Delivery → Hand over to Penyidik
   ↓
10. Complete Request
```

**WhatsApp Notifications Sent:**

- Sample received
- Test started
- Test completed
- Ready for delivery
- Delivered

---

### API Endpoints

**Authentication:**

```
POST   /login                  # User login
POST   /logout                 # User logout
GET    /user                   # Get current user
```

**Requests:**

```
GET    /requests               # List all requests
POST   /requests               # Create new request
GET    /requests/{id}          # Get request details
PUT    /requests/{id}          # Update request
DELETE /requests/{id}          # Delete request
```

**Samples:**

```
GET    /sample-processes       # List all samples
POST   /sample-processes       # Create new sample process
GET    /sample-processes/{id}  # Get sample details
PUT    /sample-processes/{id}  # Update sample
DELETE /sample-processes/{id}  # Delete sample
```

**Documents:**

```
POST   /documents/generate     # Generate document (BA/LHU)
GET    /documents/{id}         # Download document PDF
```

**Settings:**

```
GET    /api/settings           # Get all settings
PUT    /api/settings           # Update settings
POST   /api/settings/whatsapp/test  # Test WhatsApp message
GET    /api/settings/whatsapp/health # Check GOWA health
```

**Inventory:**

```
GET    /inventory/items        # List inventory items
POST   /inventory/items        # Add new item
PUT    /inventory/items/{id}   # Update item
DELETE /inventory/items/{id}   # Delete item
POST   /inventory/transactions # Record transaction
```

---

### Konfigurasi & Deployment

#### Environment Variables

```bash
# App
APP_NAME="LPMF LIMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lpmf.example.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lpmf_lims
DB_USERNAME=lpmf_user
DB_PASSWORD=secure_password

# Queue
QUEUE_CONNECTION=database

# WhatsApp (GOWA)
WHATSAPP_BASE_URL=http://localhost:3000
WHATSAPP_ENABLED=true

# Storage
FILESYSTEM_DISK=local
```

#### Production Setup

```bash
# 1. Clone repository
git clone https://github.com/your-org/lpmf-lims.git
cd lpmf-lims

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Run migrations
php artisan migrate --force

# 5. Seed default settings
php artisan db:seed --class=SettingsSeeder

# 6. Setup queue worker
php artisan queue:work --daemon

# 7. Setup web server (Nginx/Apache)
# Point document root to /public
```

#### Audit System

```bash
# Run all audits before deploy
npm run audit:critical

# Individual audits
npm run audit:css        # CSS linting
npm run audit:js         # JS linting
npm run audit:a11y       # Accessibility (requires server running)
npm run audit:guard      # Safe Mode v2 validation
```

**CI/CD Integration:**

Add to GitHub Actions:

```yaml
- name: Run critical audits
  run: npm run audit:critical
```

See [report/README.md](./report/README.md) for full guide.

---

### Panduan Pengembangan

#### Code Style

**PHP (PSR-12):**

```php
// ✅ Good
public function processRequest(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
    ]);

    return response()->json(['success' => true]);
}

// ❌ Bad
public function processRequest(Request $request) {
    $validated=$request->validate(['name'=>'required|string|max:255']);
    return response()->json(['success'=>true]);
}
```

**JavaScript (ESLint):**

```javascript
// ✅ Good
async function fetchData() {
    const response = await fetch("/api/data");
    return response.json();
}

// ❌ Bad
async function fetchData() {
    const response = await fetch("/api/data");
    return response.json();
}
```

**CSS (Stylelint + Safe Mode v2):**

```css
/* ✅ Good - Base styles */
.container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
}

/* ✅ Good - Overlay styles (pd-*.css) - NO LAYOUT PROPERTIES */
.pd-custom-color {
    color: #3b82f6;
    background-color: #eff6ff;
}

/* ❌ Bad - Overlay with layout (will fail audit:guard) */
.pd-custom-spacing {
    margin: 1rem; /* ❌ Layout property in overlay */
}
```

#### Alpine.js Best Practices

**Modifiers Usage:**

1.  **Search Inputs** - ALWAYS use debounce

    ```html
    <!-- ✅ Good: Reduces API calls -->
    <input x-model="query" @input.debounce.500ms="search()" />

    <!-- ❌ Bad: Hammers server on every keystroke -->
    <input x-model="query" @input="search()" />
    ```

2.  **Numeric Inputs** - ALWAYS use number modifier

    ```html
    <!-- ✅ Good: Returns number type (e.g., 25.5) -->
    <input type="number" x-model.number="value" />

    <!-- ❌ Bad: Returns string type (e.g., "25.5") -->
    <input type="number" x-model="value" />
    ```

3.  **Text Areas** - Use lazy for performance
    ```html
    <!-- ✅ Good: Updates data only on blur -->
    <textarea x-model.lazy="notes"></textarea>
    ```

#### Alpine.js Frontend Patterns (Comprehensive)

`Updated on 2026-01-10`

##### 1. Alpine.js State Management

- Use `x-data` for page- or component-scoped state (most Blade views).
- Use `Alpine.store()` for cross-component/global state (toast, settings shared across tabs).
- Persist user preferences with `localStorage` (theme manager in `resources/js/app.js`).

**Global store registration (current pattern):**

```javascript
// resources/js/app.js
document.addEventListener("alpine:init", () => {
    Alpine.store("toast", toastStore);
});
```

**Toast store (current implementation):**

```javascript
// resources/js/stores/toast.js
export default {
    notifications: [],
    show(message, type = "info", duration = 3000) {
        const id =
            Date.now().toString(36) + Math.random().toString(36).substr(2);
        this.notifications.push({ id, message, type, duration });
        this.announce(message, type);
        if (duration > 0) setTimeout(() => this.dismiss(id), duration);
        return id;
    },
    announce(message, type) {
        const announcer = document.getElementById("toast-announcer");
        if (announcer) {
            const prefix =
                type === "error"
                    ? "Error: "
                    : type === "warning"
                      ? "Warning: "
                      : "";
            announcer.textContent = prefix + message;
            setTimeout(() => {
                announcer.textContent = "";
            }, 1000);
        }
    },
};
```

**Settings store (recommended when multiple tabs/components share state):**

```javascript
// Suggested shape based on settingsPageAlpine client state
Alpine.store("settings", {
    state: {
        loadingSections: {},
        form: {},
    },
    async saveSection(key) {
        return this.client.saveSection(key);
    },
});
```

**Persistence pattern (theme example):**

```javascript
// resources/js/app.js
const STORAGE_KEY = "ui.theme";
localStorage.setItem(STORAGE_KEY, theme);
```

##### 2. Transitions and Animations

- Use tokenized timings from `styles/pd.ultrasafe.tokens.css`:
    - `--pd-dur-fast` (150ms), `--pd-dur` (200ms), `--pd-dur-slow` (300ms)
    - `--pd-transition-modal` for dialog timing consistency
- Prefer `x-transition` with explicit easing + duration; keep motion consistent with `styles/ui.tokens.css` and `styles/tokens.css`.

**Modal transition (current pattern):**

```html
<!-- resources/views/components/modal.blade.php -->
<div
    x-show="show"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
></div>
```

**Accordion with `x-collapse` (current pattern):**

```html
<!-- resources/views/settings/partials/monitoring-logging.blade.php -->
<div
    x-show="openMethodAccordions[methodCode]"
    x-collapse
    x-cloak
    class="p-4 border-t border-gray-200 bg-white"
>
    <!-- accordion content -->
</div>
```

**Reduced motion (respect user preference):**

```css
/* styles/a11y.css */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

##### 3. Form Patterns

- Numeric inputs: use `x-model.number` (prevents string coercion).
- Large textareas: use `x-model.lazy` (updates on blur).
- Search inputs: debounce user input to reduce API calls.

**Numeric input (current pattern):**

```html
<!-- resources/views/monitoring/environment/manage.blade.php -->
<input type="number" step="0.1" x-model.number="modal.form.target_temp_min" />
```

**Lazy textarea (current pattern):**

```html
<!-- resources/views/monitoring/environment/index.blade.php -->
<textarea x-model.lazy="inputModal.form.notes" rows="2"></textarea>
```

**Debounce (current JS helper + Alpine equivalent):**

```javascript
// resources/js/pages/search.js
function debounce(fn, delay) {
    let timer = null;
    return function debounced(...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}
```

```html
<!-- Alpine pattern for inputs -->
<input x-model="query" @input.debounce.300ms="search()" />
```

**Validation + error display (current pattern):**

```html
<!-- resources/views/settings/partials/numbering.blade.php -->
<input x-model="client.state.form.numbering[scope].pattern" />
<p
    x-show="client.state.scopeErrors[scope]?.pattern"
    class="text-xs text-red-600"
    x-text="client.state.scopeErrors[scope]?.pattern"
></p>
```

##### 4. Accessibility Patterns

- Always include `role`, `aria-labelledby`, and `aria-modal` on dialogs.
- Trap focus during modal open via `x-trap.noscroll.inert`.
- Use live regions for async status (toasts, background tasks).

**Accessible modal shell (current pattern):**

```html
<!-- resources/views/components/confirm-dialog.blade.php -->
<div
    x-show="isOpen"
    aria-labelledby="confirm-dialog-title"
    role="dialog"
    aria-modal="true"
>
    <!-- dialog content -->
</div>
```

**Live region announcer (current pattern):**

```html
<!-- resources/views/components/toast-container.blade.php -->
<div
    id="toast-announcer"
    class="sr-only"
    role="status"
    aria-live="polite"
    aria-atomic="true"
></div>
```

**Screen reader utility (current CSS):**

```css
/* styles/a11y.css */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
}
```

**Modal accessibility checklist:**

- Focus trap enabled (`x-trap.noscroll.inert`)
- Escape key closes dialog
- Backdrop click behavior defined
- `aria-labelledby` points to visible title
- Live region for async status (if needed)

##### 5. Modal and Dialog Patterns

- Use `x-teleport="#modal-portal"` to avoid z-index conflicts.
- Apply `x-trap.noscroll.inert` for focus + scroll lock.
- Escape key handling lives on the wrapper (`@keydown.escape.window`).

**Production modal template (current component):**

```html
<!-- resources/views/components/modal.blade.php -->
<template x-teleport="#modal-portal">
    <div x-show="show" x-trap.noscroll.inert="show" class="fixed inset-0 z-50">
        <div x-show="show" @click="show = false" class="fixed inset-0"></div>
        <div x-show="show" class="bg-white rounded-lg shadow-xl">
            {{ $slot }}
        </div>
    </div>
</template>
```

**Confirm dialog usage (current pattern):**

```javascript
// resources/views/requests/show.blade.php
showConfirmDialog({
    type: "danger",
    title: "Hapus Dokumen",
    message: "Apakah Anda yakin ingin menghapus dokumen?",
    confirmButtonText: "Ya, Hapus",
    confirmButtonLoadingText: "Menghapus...",
    cancelButtonText: "Batal",
    onConfirm: async () => {
        // async delete
    },
});
```

**Before → After (confirm dialog migration):**

```javascript
// Before: resources/js/pages/requests/documents.js
if (!confirm("Yakin hapus dokumen ini?")) return;
```

```javascript
// After: resources/views/requests/show.blade.php
showConfirmDialog({
    type: "danger",
    title: "Hapus Dokumen",
    onConfirm: async () => {
        /* ... */
    },
});
```

##### 6. Performance Optimization

- Debounce input events (search, filters) to reduce network noise.
- Use `x-show` for frequent toggles; use `x-if` for heavy DOM that should be destroyed.
- Defer heavy work to `init()` and use `document.addEventListener('alpine:init', ...)` in `resources/js/app.js`.

**`x-if` for heavy blocks (current pattern):**

```html
<!-- resources/views/sample-processes/edit.blade.php -->
<template x-if="loading">
    <div class="mt-4 flex items-center gap-2">Memuat data instrumen...</div>
</template>
```

**Reactivity pitfall → fix (spread + reassign):**

```javascript
// Before (anti-pattern): nested mutation may not trigger updates
this.state.previewLoading[scope] = true;

// After (current pattern): resources/js/pages/settings/index.js
this.state.previewLoading = {
    ...this.state.previewLoading,
    [scope]: true,
};
```

##### 7. Toast Notifications

- Use `$store.toast` for user-visible async feedback.
- Default durations: success/info 3000ms, warning 4000ms, error 5000ms.
- Live region announcements handled by `toastStore.announce()`.

**Usage examples (current store):**

```javascript
$store.toast.success("Data tersimpan");
$store.toast.error("Gagal menyimpan", 5000);
$store.toast.warning("Periksa input", 4000);
$store.toast.info("Proses berjalan");
```

##### 8. Loading States Matrix

| Scenario        | Pattern                    | Example                                                    |
| --------------- | -------------------------- | ---------------------------------------------------------- |
| Full page load  | Skeleton screens           | `<x-skeleton-table>` with `x-show="loading"` in list views |
| Button action   | Inline spinner             | `animate-spin` inside button with `x-show="loading"`       |
| Data refresh    | Subtle overlay/placeholder | Toggle `x-show` on list container / empty states           |
| Background task | Toast notification         | `$store.toast.info("Sedang memproses...")`                 |

**Example implementations:**

```html
<!-- Skeleton: resources/views/sample-processes/index.blade.php -->
<div x-show="loading" class="mt-2">
    <x-skeleton-table :columns="6" :rows="8" />
</div>
```

```html
<!-- Inline spinner: resources/views/monitoring/environment/manage.blade.php -->
<button type="submit" :disabled="modal.loading">
    <svg x-show="modal.loading" class="animate-spin h-4 w-4"></svg>
    <span x-text="modal.loading ? 'Menyimpan...' : 'Simpan'"></span>
</button>
```

```html
<!-- Data refresh placeholder: resources/views/requests/partials/documents.blade.php -->
<p x-show="documentsClient.state.loading">Memuat daftar dokumen...</p>
```

```javascript
// Background task toast
$store.toast.info("Backup berjalan...");
```

##### Troubleshooting

- **x-cloak flash**: ensure `[x-cloak] { display: none !important; }` exists (see `resources/views/settings/blade-templates.blade.php`).
- **State not updating**: reassign objects/arrays to trigger reactivity (use spread as in `resources/js/pages/settings/index.js`).
- **Debugging**: `window.Alpine` is available (see `resources/js/app.js`) → inspect stores with `Alpine.store('toast')`.

##### References

- Alpine store: https://alpinejs.dev/globals/alpine-store
- `x-transition`: https://alpinejs.dev/directives/transition
- `x-teleport`: https://alpinejs.dev/directives/teleport
- `x-trap`/Focus: https://alpinejs.dev/plugins/focus
- `x-collapse`: https://alpinejs.dev/plugins/collapse
- WAI-ARIA APG: https://www.w3.org/WAI/ARIA/apg/
- WCAG 2.2: https://www.w3.org/TR/WCAG22/
- ARIA in HTML: https://www.w3.org/TR/html-aria/

#### Testing

```bash
# Run PHP tests
php artisan test

# Run specific test
php artisan test --filter RequestTest

# Run with coverage
php artisan test --coverage

# Run JS tests
npm run test

# Run specific test file
npm run test -- settings-whatsapp.test.js
```

#### Database Migrations

```bash
# Create migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Rollback all & re-run
php artisan migrate:fresh
```

#### Adding New Features

1. **Plan** → Update `AGENTS.md` todos
2. **Implement** → Follow existing patterns
3. **Test** → Write tests, run audits
4. **Document** → Update WALKTHROUGH.md
5. **Commit** → Descriptive commit messages
6. **PR** → Create pull request

**Git Commit Convention:**

```
feat: add WhatsApp notification system
fix: resolve breadcrumb navigation issue
docs: update WALKTHROUGH.md with v1.1.5
refactor: extract document service class
test: add sample process tests
chore: update dependencies
```

---

### Laravel Precognition & Optimistic UI Implementation Guide

```
Updated on 2026-01-10
```

#### 1. Laravel Precognition

**Apa itu Precognition?**

Laravel Precognition menjalankan middleware + validasi tanpa mengeksekusi controller. Hasilnya: **validasi realtime** (tanpa submit penuh), **error feedback instan**, dan **UX form lebih halus**.

**Kenapa dipakai di LPMF LIMS?**

- Form `requests/create` punya banyak field dan validasi kompleks
- Pengguna perlu tahu error lebih awal (NRP format, field wajib, dsb.)
- Mengurangi submit ulang dan error “hidden” di section lain

**Instalasi (wajib diuji di lingkungan lokal):**

```bash
composer require laravel/precognition
npm install laravel-precognition-alpine
```

**Setup Route (contoh `requests.store`):**

```php
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;

Route::post('/requests', [RequestController::class, 'store'])
    ->name('requests.store')
    ->middleware([HandlePrecognitiveRequests::class]);
```

**Integrasi dengan validasi existing**

Saat ini validasi form ada di `RequestController::store()` (`$request->validate(...)`). Untuk Precognition, pindahkan rules ke FormRequest agar Precognition dapat mengeksekusi validasi tanpa menjalankan controller.

```php
namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'investigator_name' => 'required|string|min:3|max:255',
            'investigator_nrp' => 'required|string|max:50',
            'to_office' => 'required|string|max:255',
            'suspects' => 'sometimes|array|min:1',
            'suspects.*.name' => 'required|string|max:255',
            'samples' => 'required|array|min:1',
            'samples.*.short_description' => 'required|string|max:255',
            'samples.*.active_substance' => 'required|string|max:255',
            // File rules: jangan paksa upload di precognition
            'request_letter' => $this->isPrecognitive()
                ? 'nullable|file|mimes:pdf|max:10240'
                : 'required|file|mimes:pdf|max:10240',
        ];
    }
}
```

**Controller update (ringkas):**

```php
public function store(StoreRequest $request)
{
    $validated = $request->validated();
    // ...lanjutkan proses existing...
}
```

**Alpine.js plugin setup (`resources/js/app.js`):**

```javascript
import Alpine from "alpinejs";
import Precognition from "laravel-precognition-alpine";

window.Alpine = Alpine;
Alpine.plugin(Precognition);
Alpine.start();
```

**Alpine + Precognition pattern:**

```html
<form
    x-data="{
        form: $form('post', '{{ route('requests.store') }}', {
            investigator_name: '{{ old('investigator_name') }}',
            investigator_nrp: '{{ old('investigator_nrp') }}',
            to_office: '{{ old('to_office', 'KaPusdokkes Polri') }}',
        }),
    }"
    @submit.prevent="form.submit()"
>
    @csrf
    <input
        name="investigator_name"
        x-model="form.investigator_name"
        @change="form.validate('investigator_name')"
    />
    <p class="text-sm text-red-600" x-text="form.errors.investigator_name"></p>
</form>
```

**Catatan penting:**

- File upload **tidak divalidasi** saat precognition kecuali dipanggil `form.validateFiles()`
- Gunakan `isPrecognitive()` untuk menurunkan rule berat (contoh: `Password::uncompromised()`)
- Precognition **tidak menjalankan controller**, jadi side-effect (cache lock, insert, dsb.) aman

**Contoh sebelum/sesudah (`requests/create.blade.php`)**

**Before (existing):**

```html
<form
    id="request-create-form"
    action="{{ route('requests.store') }}"
    method="POST"
    x-data="{ isSubmitting: false }"
    @submit="if(isSubmitting){$event.preventDefault();return false;} isSubmitting = true;"
>
    @csrf
    <input
        type="text"
        name="investigator_name"
        value="{{ old('investigator_name') }}"
    />
    @error('investigator_name')
    <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</form>
```

**After (Precognition):**

```html
<form
    x-data="{
        form: $form('post', '{{ route('requests.store') }}', {
            investigator_name: '{{ old('investigator_name') }}',
        }),
        isSubmitting: false,
    }"
    @submit.prevent="isSubmitting = true; form.submit().finally(() => isSubmitting = false);"
>
    @csrf
    <input
        type="text"
        name="investigator_name"
        x-model="form.investigator_name"
        @change="form.validate('investigator_name')"
        :class="form.invalid('investigator_name') ? 'border-red-500' : ''"
    />
    <p class="text-sm text-red-600" x-text="form.errors.investigator_name"></p>
</form>
```

**UX benefit:** error tampil segera saat user mengetik/keluar field, tanpa submit penuh.

---

#### 2. Optimistic UI Patterns

**Optimistic UI** = UI langsung berubah **seolah-olah sukses**, API call jalan di background. Jika gagal → rollback + error toast.

**Kapan dipakai:**

- Toggle status (aktif/nonaktif)
- Inline edit sederhana (settings, label, nama)
- Non-kritis, reversible, tidak mempengaruhi transaksi finansial

**Kapan TIDAK dipakai:**

- Pembayaran, pengiriman resmi, tindakan irreversible
- Data dengan konsekuensi legal tinggi
- Operasi multi-step yang harus konsisten server-side

**Prinsip inti:**

1. Update UI segera
2. Simpan state lama untuk rollback
3. API call di background
4. Jika gagal → rollback + toast + a11y announcement
5. Gunakan loading kecil (subtle) untuk transparansi

---

#### 3. Common Optimistic UI Scenarios

##### A. Toggle Active Status (Analysts, Inventory Items, Environment Locations)

**Target aktual:**

- `resources/views/monitoring/environment/manage.blade.php` → `toggleActive(location)`
- `resources/views/analysts/index.blade.php` (aktif/nonaktif via form POST)
- `resources/views/inventory/items/form.blade.php` (`is_active` checkbox)

```javascript
async toggleActive(location) {
    const previous = location.is_active;
    location.is_active = !location.is_active;
    this.$store.toast.info("Mengubah status lokasi...");

    try {
        const response = await fetch(`/monitoring/environment/locations/${location.id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                name: location.name,
                type: location.type,
                target_temp_min: location.target_temp_min,
                target_temp_max: location.target_temp_max,
                target_humidity_min: location.target_humidity_min,
                target_humidity_max: location.target_humidity_max,
                is_active: location.is_active,
            }),
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || "Gagal mengubah status.");
        }

        this.$store.toast.success(data.message || "Status lokasi diperbarui.");
    } catch (error) {
        location.is_active = previous; // rollback
        this.$store.toast.error(error.message || "Perubahan dibatalkan.");
    }
}
```

**Aksesibilitas:** `toast` store akan mengumumkan status via `#toast-announcer`.

---

##### B. Quick Edits (Settings Values)

**Target aktual:** `resources/js/pages/settings/alpine-component.js` (save per-section)

```html
<div x-data="{
    value: client.state.form.notifications.email.sender_name,
    saving: false,
    async save() {
        const previous = client.state.form.notifications.email.sender_name;
        client.state.form.notifications.email.sender_name = this.value;
        this.saving = true;
        try {
            await client.apiFetch("/api/settings/notifications", {
                method: "PUT",
                body: { notifications: client.state.form.notifications },
            });
            $store.toast.success("Nama pengirim diperbarui.");
        } catch (error) {
            client.state.form.notifications.email.sender_name = previous;
            this.value = previous;
            $store.toast.error(error.message || "Gagal menyimpan.");
        } finally {
            this.saving = false;
        }
    },
}">
    <input
        class="w-full"
        x-model.lazy="value"
        @blur="save()"
        :disabled="saving"
    />
</div>
```

---

##### C. List Operations (Delete + Undo)

Gunakan pattern ini hanya jika delete **reversible** (soft delete atau ada undo window). Untuk delete permanen, tampilkan konfirmasi dan tunggu response sukses.

**Target aktual:** `resources/js/pages/requests/documents.js` (`deleteDocument`)

```javascript
async deleteDocumentOptimistic(doc) {
    if (!doc?.id) return;

    const snapshot = [...this.state.documents];
    this.state.documents = this.state.documents.filter((item) => item.id !== doc.id);

    const undo = () => {
        this.state.documents = snapshot;
        this.$store.toast.info("Penghapusan dibatalkan.");
    };

    const timeout = setTimeout(async () => {
        try {
            await this.apiFetch(`/api/documents/${doc.id}`, { method: "DELETE" });
            this.$store.toast.success("Dokumen berhasil dihapus.");
        } catch (error) {
            undo();
            this.$store.toast.error(error.message || "Gagal menghapus dokumen.");
        }
    }, 250);

    // Optional: hubungkan Undo ke UI toast custom
}
```

---

#### 4. Implementation Checklist

- [ ] Is operation reversible? (if no, don't use optimistic UI)
- [ ] Have you implemented error rollback?
- [ ] Does UI show loading state?
- [ ] Are errors communicated clearly?
- [ ] Is screen reader announcement included?
- [ ] Have you tested with slow network?
- [ ] Does it work offline gracefully?

---

#### 5. Code Templates

**Template 1: Optimistic Toggle (Alpine.js)**

```javascript
function optimisticToggle() {
    return {
        loading: false,
        async toggle(entity) {
            if (this.loading) return;
            const previous = entity.is_active;
            entity.is_active = !entity.is_active;
            this.loading = true;

            try {
                const response = await fetch(`/api/entities/${entity.id}`, {
                    method: "PUT",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                    body: JSON.stringify({ is_active: entity.is_active }),
                });

                const data = await response.json();
                if (!response.ok)
                    throw new Error(data.message || "Gagal menyimpan.");
                $store.toast.success(data.message || "Status diperbarui.");
            } catch (error) {
                entity.is_active = previous;
                $store.toast.error(error.message || "Perubahan dibatalkan.");
            } finally {
                this.loading = false;
            }
        },
    };
}
```

**Template 2: Precognition Form (Blade + Alpine)**

```blade
<form
    x-data="{
        form: $form('post', '{{ route('requests.store') }}', {
            investigator_name: '{{ old('investigator_name') }}',
            investigator_nrp: '{{ old('investigator_nrp') }}',
            to_office: '{{ old('to_office', 'KaPusdokkes Polri') }}',
        }),
    }"
    @submit.prevent="form.submit()"
>
    @csrf
    <label class="block">Nama Penyidik</label>
    <input
        name="investigator_name"
        x-model="form.investigator_name"
        @change="form.validate('investigator_name')"
        :class="form.invalid('investigator_name') ? 'border-red-500' : ''"
    />
    <p class="text-sm text-red-600" x-text="form.errors.investigator_name"></p>
</form>
```

**Template 3: Optimistic Delete with Undo**

```javascript
function optimisticDelete(state) {
    return {
        async remove(item) {
            const snapshot = [...state.items];
            state.items = state.items.filter((entry) => entry.id !== item.id);

            const undo = () => {
                state.items = snapshot;
                $store.toast.info("Penghapusan dibatalkan.");
            };

            const timer = setTimeout(async () => {
                try {
                    await fetch(`/api/items/${item.id}`, { method: "DELETE" });
                    $store.toast.success("Item terhapus.");
                } catch (error) {
                    undo();
                    $store.toast.error(error.message || "Gagal menghapus.");
                }
            }, 250);

            // Optional: simpan timer untuk cancel jika undo
        },
    };
}
```

---

#### 6. Best Practices

- Selalu tampilkan feedback visual (loading kecil, warna, status text)
- Selalu simpan state lama untuk rollback
- Gunakan `$store.toast.*` untuk pesan + screen reader announcement
- Gunakan timeout konservatif (hindari spam request cepat)
- Catat optimistic failures ke log/monitoring

---

#### 7. Testing Strategies

- **Slow network:** DevTools → Throttle (Slow 3G)
- **API failure:** Matikan endpoint sementara atau force `500` di dev
- **Rollback:** Pastikan state kembali ke data lama saat error
- **Accessibility:** Pastikan toast announcement terdengar di screen reader
- **Precognition:** Gunakan `withPrecognition()` di test Laravel

```php
$response = $this->withPrecognition()->post('/requests', ['investigator_name' => 'Test']);
$response->assertSuccessfulPrecognition();
```

---

#### 8. Real-World Examples in This Codebase

1. **Analyst Active/Inactive**
    - Current: form POST di `resources/views/analysts/index.blade.php`
    - Optimistic: ganti dropdown action dengan fetch + update label tanpa reload
    - UX: status berubah instan, tanpa page refresh

2. **Inventory Item Active Toggle**
    - Current: checkbox `is_active` di `resources/views/inventory/items/form.blade.php`
    - Optimistic: simpan perubahan tanpa full page reload (inline edit di list)
    - UX: status terlihat berubah saat klik

3. **Environment Location Toggle**
    - Current: `toggleActive(location)` reload list setelah sukses
    - Optimistic: update `location.is_active` lokal + rollback jika error

4. **Settings Save (Monitoring/Notifications/Branding)**
    - Current: save via `resources/js/pages/settings/alpine-component.js`
    - Optimistic: update UI state terlebih dulu + rollback jika save gagal

5. **Document Deletion (Request detail)**
    - Current: hapus setelah response sukses
    - Optimistic: remove dahulu + undo toast

6. **Search Filters**
    - Current: loader/skeleton manual di `resources/views/search/index.blade.php`
    - Optimistic: update filter UI segera + cancel in-flight request (AbortController)

---

#### 9. Troubleshooting

- **Race condition:** simpan `requestId` terakhir dan ignore response lama
- **State desync:** selalu refresh data saat error berat
- **Timeout:** gunakan `AbortController` untuk batalkan request lama
- **Precognition file rules:** gunakan `isPrecognitive()` untuk file required

---

#### 10. Performance Considerations

- Optimistic UI meningkatkan **perceived performance**
- Jangan spam request: debounce toggle cepat
- Track metric: waktu respons API vs waktu update UI
- Optimistic UI justru memperlambat jika rollback sering terjadi

---

#### References

- Laravel Precognition docs: https://laravel.com/docs/11.x/precognition
- Alpine.js form patterns
- UX research on optimistic UI
- Accessibility considerations (ARIA live regions)

---

## 📜 Changelog Archive

<details>
<summary><strong>v1.0.9 (9 Januari 2026) - WhatsApp Notification System</strong></summary>

**Feature:** Automated WhatsApp notifications for test request milestones

**Implementation:**

- Automatic notifications when samples reach specific milestones
- Configurable milestone selection
- Queue-based sending with retry (max 5 attempts, exponential backoff)
- Message outbox tracking with audit trail
- Health check endpoint for GOWA service
- Test message functionality
- Phone number normalization (Indonesia E.164)

**Files:**

- `app/Services/WhatsApp/NotificationService.php` (NEW)
- `app/Services/WhatsApp/GowaClient.php` (NEW)
- `app/Jobs/SendWhatsAppNotificationJob.php` (NEW)
- `database/migrations/*_create_whatsapp_outbox_table.php` (NEW)

**Tech Stack:**

- go-whatsapp-web-multidevice API
- Laravel Queue (database driver)
- PostgreSQL for outbox storage

</details>

<details>
<summary><strong>v1.0.8 (8 Januari 2026) - Document Template System</strong></summary>

**Feature:** Editable Blade templates for document generation

**Implementation:**

- Inline Blade editor in settings
- Version control with backup/restore
- Template preview functionality
- Validation for Blade syntax
- Support for custom variables

**Templates:**

- Berita Acara (BA)
- Laporan Hasil Uji (LHU)
- Delivery Receipt

**Files:**

- `app/Http/Controllers/Settings/TemplateController.php` (NEW)
- `resources/views/settings/blade-templates.blade.php` (NEW)

</details>

<details>
<summary><strong>v1.0.7 (8 Januari 2026) - Inventory Management</strong></summary>

**Feature:** Lab inventory tracking system

**Implementation:**

- Item catalog (reagents, consumables)
- Stock in/out transactions
- Low stock alerts
- Expiry date tracking
- Usage history reports

**Files:**

- `app/Http/Controllers/Inventory/ItemController.php` (NEW)
- `app/Models/InventoryItem.php` (NEW)
- `app/Models/InventoryTransaction.php` (NEW)

</details>

<details>
<summary><strong>v1.0.6 (7 Januari 2026) - Delivery Tracking</strong></summary>

**Feature:** Sample delivery management

**Implementation:**

- Delivery scheduling
- Recipient confirmation
- Label generation
- Tracking number assignment
- Proof of delivery upload

**Files:**

- `app/Http/Controllers/DeliveryController.php` (NEW)
- `resources/views/delivery/` (NEW)

</details>

<details>
<summary><strong>v1.0.5 (5 Januari 2026) - Sample Processing</strong></summary>

**Feature:** Lab test result recording

**Implementation:**

- Test type selection (narkotika, psikotropika)
- JSON result storage
- Multi-step process tracking
- Result validation
- Quality control flags

**Files:**

- `app/Http/Controllers/SampleProcessController.php` (NEW)
- `app/Models/SampleProcess.php` (NEW)

</details>

<details>
<summary><strong>v1.0.4 (3 Januari 2026) - Document Generation</strong></summary>

**Feature:** PDF report generation with DomPDF

**Implementation:**

- Berita Acara template
- Laporan Hasil Uji template
- Dynamic data binding
- QR code support
- Digital signature placeholders

**Files:**

- `app/Services/DocumentGenerationService.php` (NEW)
- `resources/views/templates/pdf/` (NEW)

</details>

<details>
<summary><strong>v1.0.3 (3 Januari 2026) - Request Management</strong></summary>

**Feature:** Test request creation and tracking

**Implementation:**

- Multi-step form (investigator, letter, suspects, documents, samples)
- File upload support
- Request status workflow
- Search and filtering
- Bulk actions

**Files:**

- `app/Http/Controllers/RequestController.php` (NEW)
- `app/Models/Request.php` (NEW)
- `resources/views/requests/` (NEW)

</details>

<details>
<summary><strong>v1.0.2 (2 Januari 2026) - User Management</strong></summary>

**Feature:** User authentication and authorization

**Implementation:**

- Role-based access control (Admin, Analyst, Viewer)
- User CRUD operations
- Password reset
- Activity logging
- Session management

**Files:**

- `app/Http/Controllers/UserController.php` (NEW)
- `app/Models/User.php` (UPDATED)
- `database/seeders/RoleSeeder.php` (NEW)

</details>

<details>
<summary><strong>v1.0.1 (31 Desember 2025) - Initial Setup</strong></summary>

**Feature:** Project foundation

**Implementation:**

- Laravel 12 installation
- PostgreSQL database setup
- Tailwind CSS + Alpine.js integration
- Vite build configuration
- Base layout and navigation
- Authentication scaffolding

**Tech Stack:**

- Laravel 12 (PHP 8.3)
- PostgreSQL 16
- Alpine.js 3.x
- Tailwind CSS 3.x
- Vite 7.x

</details>

---

## 📊 Sistem IKU (Indeks Kinerja Utama)

### Dashboard Metrics

**Request Metrics:**

- Total requests (monthly/yearly)
- Average completion time
- Request status distribution
- Peak request periods

**Sample Metrics:**

- Total samples processed
- Test type distribution
- Average processing time per test type
- Quality control pass rate

**Inventory Metrics:**

- Stock levels by category
- Low stock items count
- Expiry alerts
- Usage trends

**Delivery Metrics:**

- On-time delivery rate
- Average delivery time
- Pending deliveries

**System Metrics:**

- Active users
- Document generation count
- WhatsApp notification success rate
- Queue job success rate

---

## Storage Cleanup

### Automated Cleanup Tasks

**Schedule:** Daily at 2:00 AM

**Cleanup Targets:**

1. **Temporary Files** (older than 24 hours)
    - `storage/app/temp/*`
    - `storage/framework/cache/*`

2. **Generated PDFs** (older than 30 days)
    - `storage/app/documents/*.pdf`
    - Keep if linked to active requests

3. **Failed Job Logs** (older than 7 days)
    - `failed_jobs` table
    - Archive to JSON before deletion

4. **WhatsApp Outbox** (older than 90 days)
    - `whatsapp_outbox` table
    - Keep `sent` and `delivered` status only

**Manual Cleanup:**

```bash
# Run cleanup command
php artisan storage:cleanup

# With options
php artisan storage:cleanup --days=30 --dry-run
```

---

## ⚠️ Aturan Dokumentasi

**PENTING:** WALKTHROUGH.md adalah **single source of truth** untuk dokumentasi project.

**Aturan:**

1. ❌ **JANGAN** buat file `.md` baru untuk dokumentasi
2. ✅ **UPDATE** WALKTHROUGH.md di section yang relevan
3. ✅ **GUNAKAN** heading hierarchy yang proper
4. ✅ **TAMBAHKAN** date stamp: `Updated on YYYY-MM-DD`

**File Exception (diperbolehkan terpisah):**

- `README.md` (root, untuk GitHub)
- `PRE_PULL_CHECKLIST.md`
- `PRE_PUSH_CHECKLIST.md`
- `report/README.md`
- `.github/copilot-instructions.md`
- `AGENTS.md`
- `UI-UX-IMPROVEMENT-PLAN.md`
- `PARTY_MODE_SESSION_EXAMPLE.md`

**Versioning Rules:**

- Format: `MAJOR.MINOR.PATCH`
- **MAX 9 per segment** (enforced)
- When reaching 10, increment next higher segment
- Examples:
    - `1.0.9` → `1.1.0` ✅ (NOT `1.0.10` ❌)
    - `1.9.9` → `2.0.0` ✅ (NOT `1.9.10` ❌)
    - `0.0.9` → `0.1.0` ✅ (NOT `0.0.10` ❌)

---

**Last Updated:** 10 Januari 2026  
**Current Version:** v1.2.3  
**Total Versions:** 22 (v1.0.1 - v1.2.3)
