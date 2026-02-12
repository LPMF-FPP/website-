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
