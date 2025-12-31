# WALKTHROUGH - LPMF LIMS v1.0.0

> **Laboratory Information Management System untuk Laboratorium Pengujian Mutu Farmasi**
> **Dokumen ini menggabungkan PRD (Product Requirements) dan ERD (Entity Relationship)**

---

## 📋 Daftar Isi

1. [Ringkasan Produk](#ringkasan-produk)
2. [Arsitektur Sistem](#arsitektur-sistem)
3. [Entity Relationship Diagram (ERD)](#entity-relationship-diagram-erd)
4. [Modul & Fitur](#modul--fitur)
5. [Alur Kerja (Workflow)](#alur-kerja-workflow)
6. [API Endpoints](#api-endpoints)
7. [Konfigurasi & Deployment](#konfigurasi--deployment)
8. [Panduan Pengembangan](#panduan-pengembangan)

---

## Ringkasan Produk

### Tujuan
LPMF LIMS adalah sistem manajemen informasi laboratorium yang dirancang untuk:
- Mengelola **permohonan pengujian** dari penyidik kepolisian
- Melacak **sampel barang bukti** (narkotika dan zat terlarang)
- Menghasilkan **dokumen resmi** (Berita Acara, Laporan Hasil Uji)
- Mengelola **inventaris laboratorium** (reagen, consumables)
- Menyediakan **dashboard analitik** untuk monitoring kinerja

### Tech Stack
| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | Laravel (PHP) | 12.x (PHP 8.3+) |
| Frontend | Blade + Alpine.js + Tailwind CSS | Alpine 3.x, Tailwind 3.x |
| Database | PostgreSQL | 16+ |
| Build Tool | Vite | 7.x |
| PDF Generation | DomPDF | barryvdh/laravel-dompdf ^3.1 |
| Template Editor | Blade Editor | Native inline editor |
| Queue | Laravel Queue | Database driver |
| Audit Tools | Puppeteer + Lighthouse + axe-core | Development only |

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                        LPMF LIMS                            │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │  Dashboard  │  │  Requests   │  │  Samples    │         │
│  │  Controller │  │  Controller │  │  Controller │         │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘         │
│         │                │                │                 │
│  ┌──────┴────────────────┴────────────────┴──────┐         │
│  │              Service Layer                     │         │
│  │  ┌──────────────┐  ┌──────────────────┐       │         │
│  │  │ Numbering    │  │ Document         │       │         │
│  │  │ Service      │  │ Generation       │       │         │
│  │  └──────────────┘  └──────────────────┘       │         │
│  └───────────────────────┬───────────────────────┘         │
│                          │                                  │
│  ┌───────────────────────┴───────────────────────┐         │
│  │              Model Layer (Eloquent)            │         │
│  │  TestRequest │ Sample │ Document │ Investigator│        │
│  └───────────────────────┬───────────────────────┘         │
│                          │                                  │
│  ┌───────────────────────┴───────────────────────┐         │
│  │              PostgreSQL Database               │         │
│  └───────────────────────────────────────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

---

## Entity Relationship Diagram (ERD)

### Core Entities

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│   investigators  │       │   test_requests  │       │     samples      │
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │       │ id               │
│ name             │──┐    │ request_number   │    ┌──│ test_request_id  │
│ rank             │  │    │ receipt_number   │    │  │ sample_code      │
│ nrp              │  └───>│ investigator_id  │<───┘  │ short_description │
│ jurisdiction     │       │ user_id          │       │ sample_category  │
│ phone            │       │ suspect_name     │       │ sample_form      │
│ email            │       │ case_number      │       │ sample_weight    │
│ address          │       │ status           │       │ sample_status    │
│ folder_key       │       │ submitted_at     │       │ received_at      │
└──────────────────┘       │ received_at      │       │ tested_by        │
                           │ completed_at     │       │ test_methods     │
                           └────────┬─────────┘       │ active_substance │
                                    │                 └────────┬─────────┘
                                    │                          │
                           ┌────────┴─────────┐       ┌────────┴─────────┐
                           │    documents     │       │   test_results   │
                           ├──────────────────┤       ├──────────────────┤
                           │ id               │       │ id               │
                           │ investigator_id  │       │ sample_id        │
                           │ test_request_id  │       │ tested_by        │
                           │ document_type    │       │ test_method      │
                           │ filename         │       │ active_substances│
                           │ file_path        │       │ test_conclusion  │
                           │ generated_by     │       │ qc_approved      │
                           └──────────────────┘       └──────────────────┘
```

### Inventory Entities

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│ inventory_items  │       │  inventory_lots  │       │inventory_movements│
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │       │ id               │
│ item_type        │──────>│ item_id          │<──────│ item_id          │
│ name             │       │ lot_number       │       │ lot_id           │
│ brand            │       │ expiry_date      │       │ movement_type    │
│ uom              │       │ initial_qty      │       │ quantity         │
│ min_stock        │       │ current_qty      │       │ reference_type   │
│ is_hazardous     │       └──────────────────┘       │ performed_by     │
│ storage_condition│                                   └──────────────────┘
└──────────────────┘
```

### Delivery & Handover

```
┌──────────────────┐       ┌──────────────────┐
│    deliveries    │       │  delivery_items  │
├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │
│ request_id       │──────>│ delivery_id      │
│ delivered_by     │       │ sample_id        │
│ delivery_date    │       │ quantity         │
│ status           │       │ notes            │
│ collected_at     │       └──────────────────┘
└──────────────────┘
```

### Entity Relationships

| Parent | Child | Type | Description |
|--------|-------|------|-------------|
| `investigators` | `test_requests` | 1:N | Satu penyidik bisa punya banyak permohonan |
| `test_requests` | `samples` | 1:N | Satu permohonan bisa punya banyak sampel |
| `test_requests` | `documents` | 1:N | Satu permohonan bisa punya banyak dokumen |
| `test_requests` | `deliveries` | 1:N | Satu permohonan bisa punya banyak pengiriman |
| `samples` | `test_results` | 1:1 | Satu sampel punya satu hasil uji |
| `samples` | `sample_test_processes` | 1:N | Satu sampel melalui banyak tahap proses |
| `deliveries` | `delivery_items` | 1:N | Satu delivery punya banyak item |
| `inventory_items` | `inventory_lots` | 1:N | Satu item punya banyak lot/batch |
| `inventory_lots` | `inventory_movements` | 1:N | Satu lot punya banyak pergerakan stok |

---

## Modul & Fitur

### 1. Modul Permohonan Pengujian (`/requests`)

**Entitas:** `TestRequest`

| Field | Type | Description |
|-------|------|-------------|
| `request_number` | string | Nomor BA otomatis (format: BA/LPMF/XII/2025/001) |
| `receipt_number` | string | Nomor resi tracking |
| `investigator_id` | FK | Referensi ke penyidik |
| `suspect_name` | string | Nama tersangka |
| `case_number` | string | Nomor LP perkara |
| `status` | enum | pending → received → testing → completed |

**Fitur:**
- ✅ CRUD permohonan pengujian
- ✅ Upload surat resmi & foto barang bukti
- ✅ Generate Berita Acara Penerimaan (PDF)
- ✅ Tracking status realtime
- ✅ Penomoran otomatis per scope

---

### 2. Modul Sampel/Barang Bukti (`/samples`)

**Entitas:** `Sample`

| Field | Type | Description |
|-------|------|-------------|
| `sample_code` | string | Kode sampel unik |
| `sample_category` | enum | narkotika, obat, kosmetik, makanan_minuman |
| `sample_form` | enum | crystal, powder, tablet, liquid, plant |
| `sample_weight` | decimal | Berat bruto (gram) |
| `net_weight` | decimal | Berat netto (gram) |
| `sample_status` | enum | received → testing → completed |

**Kategori Sampel:**
| Category | Description |
|----------|-------------|
| `narkotika` | Narkotika & Psikotropika |
| `obat` | Obat-obatan |
| `suplemen_jamu` | Suplemen & Jamu |
| `kosmetik` | Kosmetik |
| `makanan_minuman` | Makanan & Minuman |

**Status Flow:**
```
received → preparation → instrumentation → reporting → completed → delivered
```

---

### 3. Modul Pengujian (`/sample-processes`)

**Entitas:** `SampleTestProcess`, `TestResult`

**Tahapan Pengujian:**

| Stage | Description |
|-------|-------------|
| `preparation` | Preparasi sampel (penimbangan, pelarutan) |
| `instrumentation` | Analisis instrumen (GCMS, HPLC, UV-Vis) |
| `reporting` | Pembuatan laporan hasil |

**Fitur:**
- ✅ Input hasil identifikasi fisik
- ✅ Input hasil uji GCMS/instrumen
- ✅ Upload kromatogram & spektrum
- ✅ QC approval workflow
- ✅ Generate Laporan Hasil Uji (LHU)

---

### 4. Modul Penyerahan (`/delivery`)

**Entitas:** `Delivery`, `DeliveryItem`

**Status Delivery:**
| Status | Description |
|--------|-------------|
| `pending` | Menunggu penyerahan |
| `ready` | Siap diserahkan |
| `delivered` | Sudah diserahkan |
| `collected` | Sudah diambil penyidik |

**Fitur:**
- ✅ Daftar sampel siap diserahkan
- ✅ Generate Berita Acara Penyerahan
- ✅ Survey kepuasan pelayanan
- ✅ Mark as collected

---

### 5. Modul Inventaris (`/referensi/inventori`)

**Entitas:** `InventoryItem`, `InventoryLot`, `InventoryMovement`, `InventoryBalance`

**Item Types:**
| Type | Description |
|------|-------------|
| `REAGENT` | Reagen kimia |
| `CONSUMABLE` | Bahan habis pakai (BHP) |
| `STANDARD` | Standar referensi |
| `CONTROL` | Kontrol kualitas |

**Movement Types:**
| Type | Description |
|------|-------------|
| `RECEIPT` | Penerimaan barang |
| `ISSUE` | Pengeluaran/pemakaian |
| `ADJUSTMENT` | Penyesuaian stok |
| `TRANSFER` | Transfer antar lokasi |

**Fitur:**
- ✅ Master data item (reagen, consumable, standar)
- ✅ Lot/batch tracking dengan expiry date
- ✅ Stock in/out movements
- ✅ Low stock alerts (min_stock)
- ✅ Storage condition tracking

---

### 6. Modul Dokumen & Template

**Entitas:** `Document`, `DocumentTemplate`

**Jenis Dokumen:**
| Type | Description |
|------|-------------|
| `berita_acara_penerimaan` | BA saat terima barang bukti |
| `berita_acara_penyerahan` | BA saat serah terima hasil |
| `laporan_hasil_uji` | LHU resmi laboratorium |
| `request_letter_receipt` | Tanda terima surat permohonan |
| `sample_receipt` | Tanda terima sampel |

**Template Engine:**
- **Blade Editor** - Inline code editor untuk template
- **Blade** - Server-side rendering
- **DomPDF** - PDF generation (barryvdh/laravel-dompdf)

**Penomoran Otomatis:**
| Scope | Format Example |
|-------|----------------|
| `ba` | BA/LPMF/XII/2025/001 |
| `lhu` | LHU/LPMF/XII/2025/001 |
| `tracking` | LPMF-20251230-0001 |

---

### 7. Modul Pengaturan (`/settings`)

**Entitas:** `SystemSetting`

**Grup Pengaturan:**
| Group | Settings |
|-------|----------|
| `general` | Nama lab, alamat, kontak |
| `documents` | Format penomoran, template default |
| `branding` | Logo, header, footer |

---

## Alur Kerja (Workflow)

### Alur Utama Pengujian

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  PENYIDIK   │────>│  PENERIMAAN │────>│  PENGUJIAN  │────>│ PENYERAHAN  │
│  Ajukan     │     │  Verifikasi │     │  Analisis   │     │  Serah      │
│  Permohonan │     │  Sampel     │     │  Sampel     │     │  Hasil      │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │                   │
      ▼                   ▼                   ▼                   ▼
 TestRequest         BA Penerimaan       TestResult        BA Penyerahan
 + Samples           generated           + LHU             generated
```

### Status Transitions

**TestRequest Status:**
```
pending → received → testing → completed → delivered
```

**Sample Status:**
```
received → preparation → instrumentation → reporting → completed → delivered
```

**Delivery Status:**
```
pending → ready → delivered → collected
```

---

## API Endpoints

### Public Endpoints (Tanpa Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Landing page |
| GET | `/track` | Form tracking publik |
| POST | `/track` | Submit nomor resi |
| GET | `/track/{number}.json` | API tracking JSON |
| GET | `/health` | Health check |

### Authenticated Endpoints

**Dashboard & Search:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Dashboard utama |
| GET | `/api/dashboard-stats` | Stats JSON |
| GET | `/search` | Halaman pencarian |
| GET | `/search/data` | Search results JSON |

**Requests (Permohonan):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/requests` | List permohonan |
| POST | `/requests` | Create permohonan |
| GET | `/requests/{id}` | Detail permohonan |
| PUT | `/requests/{id}` | Update permohonan |
| DELETE | `/requests/{id}` | Hapus permohonan |
| POST | `/requests/{id}/berita-acara/generate` | Generate BA |
| GET | `/requests/{id}/berita-acara/download` | Download BA PDF |

**Samples & Testing:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/samples/test` | Form input pengujian |
| POST | `/samples/test` | Submit hasil uji |
| GET | `/sample-processes` | List proses |
| GET | `/sample-processes/{id}/lab-report` | Generate LHU |

**Delivery:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/delivery` | Daftar penyerahan |
| GET | `/delivery/{id}` | Detail delivery |
| POST | `/delivery/{id}/complete` | Mark completed |
| POST | `/delivery/{id}/handover/generate` | Generate BA Penyerahan |

**Settings (Admin Only):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | Halaman pengaturan |
| GET | `/settings/data` | Get all settings |
| POST | `/settings/save` | Save settings |
| GET | `/settings/templates` | Template list |

**Inventory:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/referensi/inventori` | Dashboard inventaris |
| GET | `/referensi/inventori/items` | List items |
| POST | `/referensi/inventori/items` | Create item |
| GET | `/referensi/inventori/lots` | List lots |
| POST | `/referensi/inventori/movements` | Record movement |

---

## Konfigurasi & Deployment

### Environment Variables

```env
# Application
APP_NAME="LPMF LIMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lpmf.example.com

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=lpmf_lims
DB_USERNAME=lpmf
DB_PASSWORD=secret

# PDF Generation
PDF_DRIVER=dompdf
DOMPDF_PAPER=a4
DOMPDF_ORIENTATION=portrait

# Queue
QUEUE_CONNECTION=database

# Session & Cache
SESSION_DRIVER=database
CACHE_DRIVER=database
```

### Deployment Checklist

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Database
php artisan migrate --force

# 3. Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Storage
php artisan storage:link

# 5. Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Queue Worker (Supervisor)

```ini
[program:lpmf-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lpmf/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
user=www-data
```

---

## Panduan Pengembangan

### Struktur Folder

```
app/
├── Console/Commands/     # Artisan commands
├── Enums/               # Status enums
│   ├── SampleStatus.php
│   ├── DocumentType.php
│   └── DeliveryStatus.php
├── Http/Controllers/    # Request handlers
├── Models/              # Eloquent models
├── Services/            # Business logic
│   ├── NumberingService.php
│   └── DocumentGenerationService.php
└── View/Components/     # Blade components

resources/views/
├── components/          # Reusable UI (buttons, cards, modals)
├── requests/           # Request CRUD views
├── samples/            # Sample views
├── delivery/           # Delivery views
├── settings/           # Settings views
├── inventory/          # Inventory views
└── layouts/            # App layout (navigation, footer)

database/
├── migrations/         # Schema changes
└── seeders/           # Test data
```

### Konvensi Kode

| Type | Convention | Example |
|------|------------|---------|
| Model | Singular PascalCase | `TestRequest`, `Sample` |
| Controller | Resource pattern | `RequestController` |
| View folder | kebab-case | `sample-processes/` |
| Route name | dot notation | `requests.store` |
| Enum | PascalCase | `SampleStatus::RECEIVED` |

### Menambah Fitur Baru

```bash
# 1. Buat migration
php artisan make:migration create_new_feature_table

# 2. Buat model
php artisan make:model NewFeature

# 3. Buat controller
php artisan make:controller NewFeatureController --resource

# 4. Daftarkan route di routes/web.php

# 5. Buat views di resources/views/new-feature/

# 6. Update WALKTHROUGH.md ini (JANGAN buat file .md baru!)
```

---

## ⚠️ Aturan Dokumentasi

### JANGAN BUAT FILE .md BARU

Semua dokumentasi project harus ditambahkan ke file `WALKTHROUGH.md` ini.

Untuk menambah dokumentasi baru, tambahkan section di bagian bawah file ini.

### File Exception (boleh terpisah):
- `README.md` - Untuk GitHub
- `PRE_PULL_CHECKLIST.md` - Checklist sebelum pull
- `PRE_PUSH_CHECKLIST.md` - Checklist sebelum push
- `report/README.md` - Audit system docs
- `.github/copilot-instructions.md` - Copilot config

---

*Dokumen ini terakhir diperbarui: 30 Desember 2025*
