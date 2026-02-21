# WALKTHROUGH - LPMF LIMS v2.4.0

> **Single Source of Truth** — Pedoman terupdate terhadap codebase Laboratory Information Management System.

---

## 🚀 Quick Links

| Resource                                               | Description                             |
| ------------------------------------------------------ | --------------------------------------- |
| [AGENTS.md](./AGENTS.md)                               | Workflow rules & agent delegation guide |
| [todos.md](./todos.md)                                 | Current task list                       |
| [docs/project-overview.md](./docs/project-overview.md) | Executive summary & tech stack detail   |
| [docs/architecture.md](./docs/architecture.md)         | System modules & data flow              |
| [docs/api-contracts.md](./docs/api-contracts.md)       | REST API documentation                  |
| [report/README.md](./report/README.md)                 | Audit system guide                      |

---

## 📖 System Overview

**LPMF LIMS** adalah sistem manajemen laboratorium farmasi terintegrasi yang menangani siklus hidup pengujian sampel mulai dari penerimaan, analisis, pelaporan (LHU), hingga penyerahan kembali ke penyidik. Sistem ini dirancang dengan fokus pada:

- **Integritas Data:** Audit trail lengkap untuk setiap aksi.
- **Efisiensi:** Automasi notifikasi WhatsApp & generate dokumen PDF.
- **Kepatuhan:** Standar ISO/IEC 17025 (Manajemen Mutu Laboratorium).

### Tech Stack Summary

| Layer        | Technology                                       |
| ------------ | ------------------------------------------------ |
| **Backend**  | Laravel 12 (PHP 8.2+)                            |
| **Frontend** | Blade + Alpine.js 3 + Tailwind CSS 3             |
| **Database** | PostgreSQL (Production) / SQLite (Dev)           |
| **Realtime** | GOWA API (WhatsApp) + Laravel Reverb (WebSocket) |
| **Testing**  | Pest (Unit/Feature) + Dusk (E2E)                 |

---

## 🧩 Core Modules (Current State)

### 1. Request Management (`/requests`)

Modul penerimaan sampel dari penyidik kepolisian.

- **Flow:** Submit → Review (Kaji Ulang) → Testing → Delivery.
- **Features:**
    - **Auto-Numbering:** Format `BP/{Year}/{Counter}` dengan deteksi duplikat.
    - **Investigator DB:** Manajemen data penyidik terpusat (Pangkat, NRP, Satker).
    - **Berita Acara:** Generate otomatis PDF Berita Acara Penerimaan (Rangkap 2).

### 2. Sample Processing (`/pengujian`)

Inti dari kegiatan laboratorium. Terbagi menjadi 4 tahapan workflow:

1. **Preparation:** Persiapan sampel (weighing, extraction).
2. **Instrumentation:** Analisis menggunakan alat (GC-MS, HPLC, UV-Vis).
    - _Smart Select:_ Alat otomatis terpilih berdasarkan metode uji.
3. **Interpretation:** Analisis data & penentuan hasil (Positif/Negatif).
    - **Auto-LHU:** Dokumen Laporan Hasil Uji otomatis ter-generate saat tahap ini selesai.
4. **Delivery:** Sampel siap diserahkan.

**Guardrails:**

- Tidak bisa hapus stage jika stage berikutnya sudah dimulai.
- Tidak bisa mark `ready_for_delivery` jika LHU belum terbit.

### 3. Delivery Management (`/delivery`)

Modul penyerahan barang bukti kembali ke penyidik.

- **UI:** Stepper visual dengan progress bar & status indicators.
- **LHU Access:** Link download PDF LHU langsung di detail penyerahan.
- **Celebration Panel:** Feedback visual saat seluruh proses selesai.

### 4. WhatsApp Communication Hub (`/whatsapp`)

Pusat kontrol komunikasi berbasis GOWA API.

- **Bot Commands:**
    - `/resi {no_resi}`: Cek status pengujian.
    - `/stok`: Cek stok consumable lab.
    - `/suhu`: Cek monitoring suhu chiller/ruangan.
    - `/help`: Daftar perintah bantuan.
    - `/whitelist`: Manajemen akses admin (Super Admin only).
- **Notifications:**
    - 4 Milestone Aktif: `Request Received`, `Request Rejected`, `Ready for Pickup`, `Handover Completed`.
    - **Dynamic Greetings:** Sapaan otomatis ("Selamat Pagi Komandan...") berdasarkan waktu & pangkat.
- **Settings:**
    - **Quick Test:** Kirim pesan tes langsung dari dashboard.
    - **Template Editor:** Edit template pesan dengan placeholder `{nama}`, `{resi}`, `{status}`.
    - **Magic Compose:** AI-assisted drafting untuk pesan broadcast.

### 5. Inventory Management (`/referensi/inventori`)

Manajemen stok bahan habis pakai (consumables) dan reagen.

- **Dashboard:**
    - **Stock Overview:** Health bar visual untuk stok menipis.
    - **Fast Moving:** Analisis item paling sering keluar.
    - **Quick Actions:** Shortcut untuk transaksi Masuk/Keluar/Transfer.
- **Sample Disposal:** Modul pemusnahan sampel sisa uji dengan Berita Acara Pemusnahan.
- **Alerts:** Notifikasi WhatsApp otomatis untuk Low Stock & Near Expiry.

### 6. Monitoring & Quality (`/monitoring`)

- **Environment:** Monitoring suhu & kelembaban chiller/ruangan (Input manual via WA / IoT).
- **IKU (Indeks Kinerja Utama):** Tracking performa lab (Kecepatan & Kepuasan) dengan mode **Triwulan**.
- **Consolidated Report:** Laporan bulanan/tahunan agregat untuk pimpinan.

### 7. Settings & Configuration (`/settings`)

Pusat konfigurasi sistem dengan navigasi tab:

- **Numbering:** Format nomor surat, LHU, sampel. Termasuk **Repair Tool** untuk fix sequence yang lompat/duplikat.
- **Templates:** Editor template dokumen (Blade support).
- **Branding:** Upload logo, kop surat, dan pengaturan PDF.
- **Permissions:** Matrix akses user granular (View/Create/Edit/Delete) per modul.

---

## 🏗️ Infrastructure & DevOps

### Queue System

Menggunakan **Laravel Queue** dengan driver `database`.

- **Worker:** Dijalankan via `systemd` (`laravel-queue.service`).
- **Prioritas:** `high` (WhatsApp/Email), `default` (PDF Gen), `low` (Maintenance).

### Scheduler

Cron job (`routes/console.php`) menjalankan:

- `inventory:check-alerts`: Cek stok/expiry (08:00 WIB).
- `monitoring:check-alerts`: Cek suhu chiller (Hourly).
- `app:backfill-missing-preparation`: Self-healing data repair.

### GOWA Integration

WhatsApp service berjalan di container Docker terpisah.

- **Endpoint:** `http://localhost:3000`
- **Auth:** Basic Auth + API Key.
- **Webhook:** `POST /api/whatsapp/webhook` (HMAC Secured).

---

## 📰 Recent Changes (v2.4.x)

### v2.4.3 (21 Februari 2026) - QMH Dokumen Pendukung untuk SOP/IK

- **New Module:** Penambahan modul **Dokumen Pendukung QMH** untuk upload, manajemen versi, dan pengelompokan dokumen pendukung per clause (4-8).
- **Editor Integration:** Tombol **Link Pendukung** kini tersedia di editor SOP/IK (termasuk schema-driven editor) untuk menyisipkan tautan dokumen pendukung langsung ke konten revisi.
- **Security Hardening:** Upload file diperketat dengan blokir SVG (XSS risk), validasi magic number (anti spoofing), serta verifikasi integritas file berbasis SHA-256.
- **Storage Compatibility:** Alur akses file menggunakan `Storage::download()`/`Storage::response()` agar storage-agnostic (local/S3/minIO), plus throttle khusus endpoint unduhan.
- **Backward Compatibility:** Dokumen SOP/IK yang sudah ada tetap berjalan tanpa migrasi data manual; dokumen pendukung dapat langsung di-link ke dokumen lama.

### v2.4.2 (19 Februari 2026) - QMH WhatsApp Workflow Actions & Security Hardening

- **QMH-WhatsApp Workflow:** Otomatisasi task review/approval dari transisi QMH ke `staff_tasks`, termasuk due date 24 jam dan notifikasi WA per tahap.
- **Command Action `/qmh`:** Reviewer dan approver sekarang bisa `approve/reject` langsung via WhatsApp dengan validasi assignee-bound, action code one-time, expiry, dan rate-limit percobaan.
- **Webhook Security:** Inbound webhook WA sekarang fail-closed saat secret kosong/tidak valid, plus replay protection dengan dedupe `provider_message_id` dan fingerprint fallback.
- **Attachment Reliability:** Penambahan `sendFile()` GOWA + fallback text-only, guard MIME/ukuran file, retry backoff+jitter, dan redaksi action code pada log audit.
- **Operational Hardening:** Endpoint restart queue diproteksi lebih ketat (disable-by-default di production, token wajib, optional IP allowlist).

### v2.4.1 (18 Februari 2026) - QMH FR-v2 Hardening & Backup Resilience

- **QMH FR-v2:** Hardening alur create/edit/review untuk dokumen FR v2, termasuk guard policy dan fallback governance template aktif.
- **Workflow Integrity:** Penambahan idempotency key + event workflow FR v2 untuk mencegah transisi ganda pada skenario retry/concurrent request.
- **Quality Gate:** Penambahan cakupan test QMH (Feature/Unit + Browser workflow create/edit) untuk menurunkan risiko regresi pada modul mutu.
- **Backup Stability:** Proses archive storage sekarang toleran direktori unreadable (`tar --ignore-failed-read`) dan mengecualikan jalur temporer `private/qmh/tmp`.

### v2.4.0 (12 Februari 2026) - LHU Security & Stability

- **Critical Security Fix:** Scope LHU document query `by sample_id` untuk mencegah kebocoran data antar sampel.
- **Stability:** Menggunakan `stage_order` alih-alih `completed_at` untuk determinasi label stage yang lebih akurat.
- **Data Repair:** Seeder khusus untuk memperbaiki data `LS065I2026` yang corrupt.
- **Docs:** Massive documentation update & consolidation.

### v2.3.2 (10 Februari 2026) - Settings Page Hardening

- **Fix:** Hapus debug UI yang bocor ke production.
- **Fix:** Eliminasi 17 `console.log` dan method duplikat di JS.
- **Feature:** Error recovery UI jika API settings gagal load.
- **A11y:** Penambahan ARIA roles (tablist, tabpanel) untuk aksesibilitas sidebar.

### v2.3.1 (10 Februari 2026) - Process Guardrails

- **Fix:** Mencegah penghapusan stage `preparation` jika `instrumentation` sudah dimulai.
- **Feature:** Validasi server-side `markReadyForDelivery()`: Wajib 3 stage complete + LHU terbit.
- **Tool:** Command `app:backfill-missing-preparation` untuk restore data stage yang hilang.

---

## 📜 Changelog Archive

<details>
<summary><strong>Klik untuk melihat riwayat versi lama (v1.x - v2.2.x)</strong></summary>

### v2.2.x - v2.3.0 (Feb 2026)

- **Delivery UX:** Redesign halaman delivery dengan stepper progress visual.
- **LHU Access:** Link download LHU langsung di delivery detail.
- **Settings Tab:** Redesign settings jadi tab horizontal + Whitelist Manager UI.

### v2.0.x - v2.1.x (Feb 2026)

- **Inventory Dashboard v2:** Total overhaul UI inventory + Fast Moving analysis.
- **AI Magic Compose:** Integrasi LLM untuk drafting pesan WhatsApp.
- **Sample Disposal:** Sistem manajemen pemusnahan sampel sisa uji.

### v1.x (Jan 2026)

- **WhatsApp Hub:** Sentralisasi fitur komunikasi (Tasks, Broadcast, Reminders).
- **Numbering Repair:** Tool untuk fix nomor urut yang lompat.
- **Permissions:** Granular permission system (User-level overrides).
- **Theme:** "Clinical Precision" UI theme implementation.
- **WhatsApp Bot:** Initial implementation of `/resi` and `/help` commands.

</details>

---

## Quality/QMH Form Builder (Formulir/FR)

### Context

QMH `FR` (UI) disimpan sebagai `doc_type=formulir` (DB). Saat ini schema pertanyaan formulir disimpan sebagai JSON di metadata template dan dipakai untuk:

- Render input di halaman create/edit QMH.
- Render structured preview (browser) dan output PDF.

Perubahan terbaru sudah memastikan structured preview dan PDF untuk `formulir` ditampilkan dalam format tabel bernomor agar hasil lebih "form-like".

### Goals

- Menggantikan editing schema berbasis textarea JSON menjadi **Form Builder UI** yang mudah dipakai admin.
- Menambah tipe field umum formulir (incremental) dan memastikan konsistensi render di:
    - Form input (create/edit)
    - Structured preview
    - PDF
- Menambah validasi server-side untuk jawaban formulir berbasis schema (required + tipe).
- Menjaga backward compatibility untuk dokumen lama (schema versi lama & jawaban lama).

### Non-Goals (v1)

- Workflow QMH (draft/submit/review/approve) tidak diubah.
- Tidak membangun editor DOCX/OnlyOffice.
- Tidak membangun grid/repeating table yang kompleks (ditunda ke v2).

### Canonical Schema (Template Metadata)

Schema disimpan di `QmhTemplate.metadata.form_schema` dan digunakan sebagai payload `schema` di UI.

Struktur v1 (existing + extension):

```json
{
    "version": 1,
    "doc_type": "fr",
    "questions": [
        {
            "id": "field_a",
            "label": "Kolom A",
            "type": "text",
            "required": false,
            "help": "Opsional help text",
            "placeholder": "Contoh isi"
        }
    ]
}
```

#### Question Types (v1)

- `section`: pemisah/judul (tidak punya jawaban)
- `text`: string satu baris
- `textarea`: string multi baris
- `list`: list item (array string) atau rich-text HTML (legacy supported)
- `select`: pilihan satu (string) dengan `options`
- `checkbox`: boolean
- `date`: string format `YYYY-MM-DD`
- `number`: string/number (disimpan string untuk konsistensi JSON)

Untuk `select`, tambahkan:

```json
{
    "id": "status",
    "label": "Status",
    "type": "select",
    "required": true,
    "options": [
        { "value": "ok", "label": "Sesuai" },
        { "value": "nok", "label": "Tidak Sesuai" }
    ]
}
```

### Answers Model (Revision)

Jawaban disimpan di `QmhDocumentRevision.answers_json` sebagai object dengan key = `question.id`.

Kontrak jawaban v1:

- `text`/`textarea`/`date`/`number`/`select`: string (boleh empty string; required validated)
- `checkbox`: boolean
- `list`: array of string (preferred). Legacy: string HTML yang berisi list masih diterima untuk backward compatibility.
- `section`: tidak ada key (atau diabaikan jika ada).

### Validation Rules (Server-Side)

Implementasikan validasi terpusat (service/support) untuk memastikan:

- Semua `question.id` unique, non-empty, max length (mis. 64), pattern aman (`[a-z0-9_]+`).
- Untuk `select`: `options` wajib ada, `value` unique.
- Saat save answers:
    - Required: value tidak blank (text/textarea/select/date/number), list minimal 1 item non-blank, checkbox harus boolean.
    - Unknown answer keys: boleh disimpan (compat) tapi ditandai untuk UI "unmapped answers" (future).

### Rendering Requirements

- Create/Edit: field renderer berdasarkan `type`.
- Structured preview:
    - `section` dirender sebagai row spanning (future); v1 bisa dirender sebagai label tanpa nomor.
    - Blank values tetap tampil placeholder kosong agar form tidak "lompat".
- PDF:
    - Output tabel bernomor (No/Label/Isi).
    - Row height adaptif minimal per tipe (text/list/textarea) + checkbox/date/select readable.

### Builder UI (Template Editor)

Lokasi: halaman edit template QMH.

Kemampuan minimum:

- Add question (pilih type, label, auto-generate id).
- Edit question properties (label, required, help, placeholder, options).
- Reorder questions (drag/drop) dan delete.
- Live JSON preview + hidden textarea (`form_schema_json`) tetap jadi source-of-truth untuk submit.
- Guardrails: mencegah duplicate id, invalid JSON, dan menampilkan error inline.

### Backward Compatibility

- Schema versi lama tanpa field tambahan tetap valid.
- Jawaban existing tidak dimodifikasi saat schema berubah; renderer harus toleran terhadap missing ids.
- Untuk `list`: dukung kedua representasi (array dan HTML string).

### Testing Strategy

- Pest:
    - Unit test validator schema (valid/invalid cases).
    - Feature test update template menyimpan `form_schema_json` hasil builder.
    - Feature test create/save dokumen FR dengan required fields.
- Dusk (optional, setelah v1 stabil): drag/drop reorder + submit.

### Acceptance Criteria (v1)

- Admin dapat membuat & mengedit schema FR dari UI builder tanpa mengetik JSON.
- Dokumen FR create/edit render field sesuai schema.
- Structured preview dan PDF menampilkan hasil dalam tabel yang konsisten untuk semua tipe v1.
- Validation server-side menolak submit/save jika required field kosong.

---

## Quality/QMH Templates (HTML-First, DOCX Optional)

### Context

Sebelumnya, pembuatan template QMH bersifat DOCX-centric: admin wajib upload DOCX untuk membuat/aktivasi template. Padahal eksekusi dokumen QMH di aplikasi sudah HTML-first (konten dokumen berasal dari `metadata.content_html`).

### Goals

- Admin bisa membuat & mengaktifkan template SOP/IK/FR tanpa upload DOCX (HTML-only).
- DOCX tetap didukung sebagai sumber (import awal) dan arsip, tapi tidak wajib.
- Preview template tetap bisa dilakukan walau template tidak punya DOCX.

### Non-Goals

- Tidak membangun roundtrip DOCX <-> HTML yang lossless.
- Tidak mengganti mekanisme versioning template (tetap per `doc_type` + `version`).

### Data Model

- `qmh_templates.source_docx_path`: nullable (sudah).
- `qmh_templates.metadata.content_html`: canonical konten template.

### Create Rules (SOP/IK/FR)

- Minimal salah satu harus ada:
    - `file` (DOCX), atau
    - `content_html` (dari editor browser)

Resolusi konten saat create:

1. Jika `content_html` non-blank => gunakan sebagai `metadata.content_html`.
2. Else jika `file` ada => store DOCX + extract HTML => simpan sebagai `metadata.content_html`.
3. Else => reject (validasi).

### Preview Rules

- Jika template punya DOCX yang valid di storage:
    - tetap tampilkan Office viewer + tombol "Buka File Langsung".
- Jika tidak punya DOCX:
    - tampilkan preview HTML dari `metadata.content_html`.

### Security Notes

- HTML preview harus memakai sanitasi yang sama dengan editor/rendering dokumen (hindari script injection).
- Route preview file DOCX (signed URL) tetap 404 untuk template HTML-only.

---

## Quality/QMH Formulir (FR) - Pertanyaan Per Dokumen (Schema Snapshot per Revision)

### Problem

Saat ini schema FR dibaca langsung dari `QmhTemplate.metadata.form_schema` pada saat:

- Create dokumen FR (validasi jawaban)
- Save konten revisi FR (validasi jawaban)
- Submit for review (guard validasi)
- Rendering PDF

Konsekuensinya: jika template schema diubah, dokumen FR lama bisa "berubah" schema-nya (drift) dan berisiko mematahkan validasi/PDF.

### Goal

- FR dapat menambah/mengubah pertanyaan saat pembuatan dokumen (dan opsional saat edit draft).
- Schema FR yang dipakai harus "menempel" ke revisi (snapshot) agar stabil untuk audit + PDF.

### Scope Rules

- Schema FR hanya boleh diedit saat `revision.status = draft`.
- Akses edit schema mengikuti aturan lock (hanya lock owner bisa menyimpan perubahan).

### Data Model (Proposed)

Tambahkan kolom baru:

- `qmh_document_revisions.form_schema_json` (jsonb, nullable)

### Schema Resolution Precedence

Semua tempat yang butuh schema (validate/render/PDF) harus resolve schema dengan urutan:

1. Jika `revision.form_schema_json` ada (array) => pakai.
2. Else jika `revision.template.metadata.form_schema` ada => pakai.
3. Else fallback:
    - SOP/IK: default schema (existing behavior)
    - FR: `questions = []`

### Create FR UX

- Create dokumen FR menampilkan Form Builder inline.
- Default schema awal berasal dari template aktif (kalau ada), tapi user boleh edit sebelum submit.
- Payload create menyertakan:
    - `answers_json` (jawaban)
    - `form_schema_json` (optional override/snapshot)

Persist:

- `answers_json` => `QmhDocumentRevision.answers_json`
- `form_schema_json` => `QmhDocumentRevision.form_schema_json`

### Validation

- Jika `form_schema_json` dikirim:
    - validate schema (gunakan validator schema existing)
    - validate answers terhadap schema override
- Jika tidak dikirim:
    - validate answers terhadap schema template

### PDF & Audit

- PDF harus menggunakan schema hasil resolusi precedence di atas.
- Ini memastikan print output konsisten untuk revisi yang sudah dibuat.

---

## Quality/QMH UI/UX Redesign (Wireframes + IA)

### Visual Direction

- "Clinical Precision" yang konsisten dengan theme existing: permukaan putih hangat, border slate, aksi primer hijau/teal (hindari dominasi biru).
- Status jelas (success/warning/danger/info), CTA tidak berlebihan, layout terstruktur dan audit-ready.

### Global Layout Rules (Semua halaman /quality/\*)

- Breadcrumbs wajib tampil (clickable) untuk wayfinding.
- Subnav QMH tabs selalu tampil: `Overview | Dokumen | Buat Dokumen | Template`.
- Hindari nested container yang menggandakan padding (gunakan container dari layout utama saja).

### Wireframes (Desktop)

#### QMH Overview (/quality)

```text
[Header + Breadcrumbs + Tabs]

Title: Mutu (QMH)
KPI Row: [Kepatuhan] [Dok Aktif] [Perlu Review] [Temuan]

Main (2/3):
  - Aktivitas terbaru (list)
  - Dokumen perlu perhatian (table)

Right Rail (1/3):
  - Tindakan cepat: [Buat Dokumen] [Kelola Template] [Lihat Semua Dokumen]
  - Kepatuhan per klausul/unit (mini summary)
```

#### Dokumen (/quality/documents)

```text
[Header + Breadcrumbs + Tabs]

Title: Dokumen QMH
Search + Filters + Active filter chips

Table:
  Kode | Judul | Status | Versi | Updated | Aksi (Buka)

Mobile fallback: kartu list dengan badge status + CTA Buka
```

#### Buat Dokumen (/quality/create)

```text
[Header + Breadcrumbs + Tabs]

Title: Buat Dokumen QMH
Stepper: (1) Pilih Template -> (2) Metadata -> (3) Konten/Pertanyaan -> (4) Review

Step 1:
  - Kartu template + Preview + Pilih

Step 3 (FR):
  - Form Builder (pertanyaan) + Form input (jawaban) + Preview ringkas
```

#### Edit Dokumen (/quality/{doc}/edit)

```text
[Header + Breadcrumbs + Tabs]

Title + Meta: Status, Versi, Lock state

Main (2/3): Editor
Right Rail (1/3): Workflow actions + Checklist + Preview PDF
```

#### Template (/quality/templates)

```text
[Header + Breadcrumbs + Tabs]

Title: Template QMH
Default view: scan/manage templates (table)
Create/upload is collapsible or separate section to avoid pushing the table down
```

#### Edit Template (/quality/templates/{id}/edit)

```text
Tabs (internal): Metadata | Content | Form Schema (FR)
Right Rail: Dampak perubahan + publish rules + validation
```

### Mobile Notes

- Tabs menjadi segmented/scroll; breadcrumbs jadi "Kembali" + judul singkat.
- Right rail berubah menjadi bottom sheet (Workflow/Checklist).
- Stepper menjadi progress bar (Step X/4) dengan CTA sticky bottom.
