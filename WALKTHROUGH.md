# WALKTHROUGH - LPMF LIMS v2.6.2

> **Single Source of Truth** — Pedoman terupdate terhadap codebase Laboratory Information Management System.

---

## 🚀 Quick Links

| Resource                                               | Description                             |
| ------------------------------------------------------ | --------------------------------------- |
| [AGENTS.md](./AGENTS.md)                               | Workflow rules & agent delegation guide |
| [docs/project-context.md](./docs/project-context.md)   | Lean rules for AI agents                |
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

## 📰 Recent Changes (v2.6.x)

### v2.6.2 (13 Agustus 2026) - Audit Outbound WhatsApp & Retry Aman

- **Log Outbound Lengkap:** Seluruh pengiriman melalui GOWA kini dicatat per pesan, termasuk milestone, broadcast, reminder, notifikasi tugas, workflow QMH, laporan gabungan, alert inventori/monitoring, balasan command, dan pesan uji Hub.
- **Riwayat Attempt & Status Aman:** Setiap pengiriman menyimpan envelope sebelum request provider dan riwayat percobaan dengan klasifikasi `sent`, `failed`, `unknown`, atau `blocked`. Detail error provider yang sensitif tidak ditampilkan di tab Log.
- **Retry Terkendali:** Admin `manage-settings` dapat mengulang hanya pesan dengan kegagalan provider yang terkonfirmasi. Timeout, koneksi terputus, dan status tidak pasti tetap diblokir agar tidak berisiko mengirim pesan ganda.
- **Lampiran Privat:** PDF atau lampiran yang dikirim dari file sementara disalin lebih dahulu ke storage privat, sehingga retry dapat memakai snapshot yang sama setelah file sumber dibersihkan.
- **Audit & Proteksi Aksi:** Setiap permintaan retry dicatat ke activity log, dibatasi rate limit, dan endpoint retry tidak menerima nomor, isi pesan, atau lampiran baru dari browser.
- **Keandalan Reminder:** Reminder terjadwal menggunakan job unik dan key idempotensi per penerima untuk mencegah pengiriman ganda saat scheduler atau worker tumpang tindih.
- **Navigasi Log:** Tab Log WhatsApp kini mendukung pagination dengan state halaman pada URL, sehingga pesan gagal historis tetap dapat ditemukan dan ditinjau.

### v2.6.1 (27 Juli 2026) - Integritas Form Sampel & Remediasi Dependency

- **Penyimpanan Multi-Sampel:** Memperbaiki struktur HTML form create/edit permintaan yang sebelumnya membuat sampel tambahan terlihat di halaman tetapi berada di luar elemen `<form>`, sehingga hanya sampel pertama yang terkirim ke server.
- **Tes Regresi Form:** Menambahkan verifikasi DOM untuk memastikan container sampel selalu berada di dalam form create dan edit.
- **Remediasi Keamanan NPM:** Memperbarui dependency langsung dan transitif yang terdampak, termasuk Axios, PostCSS, Concurrently, ESLint, `glob`, dan `minimatch`. Hasil `npm audit` turun dari 9 temuan (8 high, 1 critical) menjadi 0 kerentanan.
- **Modernisasi Tooling:** Migrasi ESLint ke native flat config, mengganti `npm-run-all` dengan `npm-run-all2`, serta menghapus plugin TypeScript, Vue, JSX, dan Tailwind Vite yang tidak digunakan codebase.
- **Quality Gates:** Instalasi bersih `npm ci`, ESLint, Stylelint, audit critical, build Vite, dan unit test JavaScript telah lulus.

### v2.6.0 (24 Juli 2026) - Buku Tamu, Dashboard Redesign & Form Non-POLRI

- **Buku Tamu (Guest Book):** Fitur pencatatan kunjungan tamu laboratorium. Setiap permohonan pengujian baru dan penyerahan selesai otomatis mencatat kunjungan. Mendukung 9 jenis keperluan: Permohonan Pengujian, Pengambilan Hasil Pengujian, Audit Mutu, Inspeksi, Pelatihan, Kunjungan (Studi Banding), Service Mesin, Magang, Lainnya.
- **Mode Form Dua Kategori:** Untuk keperluan pengujian/pengambilan (kasus), form menampilkan Pemilik Kasus dan Pihak Yang Datang dengan checkbox "Pemilik kasus = pihak yang datang". Untuk keperluan non-kasus, form menyembunyikan Tersangka & Saksi Ahli dan menampilkan field data tamu lengkap.
- **Tujuan Pengujian:** Kategori bukan anggota Polri kini memiliki field "Tujuan Pengujian" sebagai pengganti data Tersangka dan Saksi Ahli.
- **Widget Dashboard Buku Tamu:** Widget ringkas di dashboard utama menampilkan statistik kunjungan hari ini, daftar tamu terbaru dengan avatar inisial, date picker untuk navigasi ke halaman Buku Tamu dengan filter tanggal, dan month picker untuk download rekap bulanan.
- **FAB Catat Kunjungan:** Tombol aksi utama "Catat Kunjungan" dipindahkan ke Floating Action Button di pojok kanan bawah halaman Buku Tamu, menggantikan posisi sebelumnya di header yang bersaing dengan kontrol sekunder.
- **Rekap Bulanan PDF:** Download laporan rekap kunjungan per bulan dengan kop surat yang sama dengan BA ST dan BA Penerimaan, dilengkapi ringkasan statistik, rincian per keperluan dengan chart batang, dan daftar lengkap kunjungan.
- **Auto-Create & Visitor Population:** Kunjungan otomatis kini mengisi data visitor (nama, identitas, relasi, telepon) dari data penyidik, bukan lagi meninggalkan kolom kosong.
- **Terminologi Pengguna:** Seluruh istilah "Pelanggan" diganti menjadi "Pengguna" di 11 file, dan "Pelanggan" pada BA Serah Terima diganti menjadi "Penerima".
- **WhatsApp Milestone:** Milestone #5 (Selesai) pada RESI tracking WhatsApp kini tercentang saat status permintaan `completed`.
- **Validasi & Keamanan:** visitor_phone regex, visit_time range (06:00-22:00), visit_date lower bound (2024-01-01), notes max:2000, policy checkout, viewAny tightened, authorization di show/edit/destroy.

### v2.5.7 (8 Juli 2026) - Pembersihan Storage & Code Review Hardening

- **Pembersihan Storage (Settings > Manajemen Dokumen):** Fitur deteksi dan pembersihan folder investigator orphan serta dokumen duplikat kini dilengkapi mekanisme pratinjau dua langkah — klik "Pratinjau" untuk melihat apa yang akan dihapus, lalu "Konfirmasi Hapus" untuk eksekusi. Flow ini mencegah penghapusan tidak sengaja dan memberikan transparansi penuh sebelum data dihapus.
- **Filesystem Scanner Performance:** Pemindaian duplikat di filesystem kini dibatasi hanya pada direktori `investigators/` (tidak lagi seluruh public disk) dan menggunakan lookup O(1) via `array_flip` sebagai pengganti `in_array` linier untuk path yang sudah terdaftar di database.
- **Deteksi Duplikat Filesystem di Cron:** Perintah `storage:cleanup-duplicates` kini juga mendeteksi file duplikat di filesystem yang tidak tercatat di database, menyelaraskan perilaku command line dengan controller API.
- **Prune Soft-Deleted Documents:** Perintah baru `storage:prune-soft-deleted` membersihkan dokumen yang sudah soft-deleted lebih dari 30 hari secara permanen. Dijadwalkan mingguan setiap Minggu 04:00 dengan chunking 500 record per batch untuk mencegah OOM.
- **Preview PDF Popup Fix:** Mekanisme popup pratinjau PDF diperbaiki agar tidak meninggalkan tab kosong saat preview gagal, dan menggunakan satu `window.open` setelah fetch selesai untuk menghindari popup blocker di browser modern.
- **Kop Surat Contact Line Guard:** Separator `•` pada baris kontak kop surat kini hanya muncul jika alamat terisi, mencegah tampilan leading bullet pada 5 template utama.
- **QMH Document Header:** Nama laboratorium pada header dokumen QMH kini mendukung `word-break` untuk menangani teks panjang tanpa bergantung pada `<br>` eksplisit.
- **Branding Empty-String Normalization:** Field `address`, `phone`, `email`, `website`, dan `lab_name` pada branding settings kini dinormalisasi dari string kosong menjadi `null` saat disimpan, memastikan fallback default bekerja dengan benar.

### v2.5.6 (8 Juli 2026) - Kop Surat Terpusat & Backup Hardening

- **Kop Surat Terpusat (Settings Branding):** Nama instansi, nama laboratorium, alamat, telepon, email, dan website kini dikelola dari satu tempat di `Settings > Branding & PDF` dan diterapkan ke seluruh dokumen berkop — Laporan Hasil Uji, Berita Acara Penerimaan, BA Penyerahan, BA Pemusnahan, laporan lingkungan/instrumen/penimbangan, QMH, dan LHU.
- **Ukuran Font Diperbesar:** Nama Instansi dinaikkan menjadi 14pt dan Nama Laboratorium menjadi 12.5pt di semua template PDF, mengatasi inkonsistensi ukuran antar dokumen yang sebelumnya bervariasi dari 9pt hingga 12.5pt tanpa ukuran eksplisit.
- **Label Kontak Disederhanakan:** Prefix `Telp/Fax:` diganti menjadi `Telp:` di seluruh dokumen kop surat dan `AbstractContextResolver`.
- **Fix PDF Preview Popup Blocker:** Tombol `Pratinjau PDF` di Settings kini membuka tab baru sebelum request async agar tidak diblokir popup blocker browser.
- **Backup Process Timeout:** Timeout proses backup darurat dinaikkan menjadi 30 menit (1800 detik) untuk mencegah kegagalan pada environment dengan volume data besar.
- **Backup Unreadable Path Exclusion:** Proses backup kini otomatis mengecualikan path storage yang tidak dapat dibaca (misalnya samples symlink yang belum ditautkan) tanpa menggagalkan keseluruhan proses backup.
- **WhatsApp Status Preview Alignment:** Format penomoran resi tracking pada WhatsApp status preview dan contoh pelacakan diselaraskan dengan format `LPMF{SEQ:3}{MM}{YY}` yang berlaku, sehingga petugas dapat memberikan panduan pelacakan yang konsisten kepada penyidik.

### v2.5.5 (24 Juni 2026) - Delivery Multi-Suspect & LHU Pro Justitia

- **Delivery Multi-Suspect Display:** Modul `/delivery` dan `/delivery/{request}` sekarang membaca relasi `suspects` sebagai sumber utama data tersangka, sehingga request dengan lebih dari satu tersangka tidak lagi terpotong ke satu nama legacy saja.
- **Delivery History Search Alignment:** Pencarian riwayat penyerahan kini ikut mencocokkan nama pada relasi `suspects`, sehingga nama tersangka tambahan yang sudah tampil di UI juga dapat ditemukan lewat kolom pencarian history.
- **Legacy Fallback Safety:** Delivery tetap fallback ke `test_requests.suspect_name` saat request lama belum memiliki row pada tabel `suspects`, agar data historis tetap terbaca tanpa migrasi manual.
- **LHU Pro Justitia for Polri Requests:** Dokumen Laporan Hasil Uji sekarang menampilkan label italic `&ldquo;Pro Justitia&rdquo;` hanya bila permintaan berasal dari penyidik/anggota Polri (`investigator.is_polri = true`), baik pada jalur template aktif `LHU` maupun fallback Blade legacy.
- **Template Path Parity:** Generator LHU kini menyalurkan token `pro_justitia_text` ke template aktif dan sekaligus memiliki injeksi HTML final sebagai pengaman, sehingga perubahan tetap berlaku walau template aktif database belum diperbarui manual.
- **Title Alignment Polish:** Label `&ldquo;Pro Justitia&rdquo;` diselaraskan menjadi rata kiri tepat di bawah judul `LAPORAN HASIL UJI`, agar terasa menyatu dengan heading dokumen dan tetap konsisten pada hasil PDF/HTML akhir.
- **Regression Coverage:** Coverage LHU diperluas untuk memastikan request Polri menampilkan `&ldquo;Pro Justitia&rdquo;`, request non-Polri tidak menampilkannya, dan jalur render template aktif tetap mengikuti aturan yang sama.

### v2.5.4 (22 Juni 2026) - Remaining Label Sheet Reconciliation

- **Remaining Label Sheet Reconciliation:** Cetak `labels/remaining/request/{id}/sheet`, `labels/remaining/{evidenceUnit}/all`, dan label sisa single sekarang merekonsiliasi label sisa tunggal dari `jumlah diserahkan - jumlah diuji`, sehingga data stale pada `remaining_units.qty_remaining` tidak lagi membuat `Qty Sisa` tercetak lebih besar dari sisa riil sampel.
- **Self-Healing Remaining Label Data:** Alur pengambilan label sisa kini sekaligus menyinkronkan ulang row label sisa tunggal yang stale ke nilai rekonsiliasi terbaru, sehingga kasus historis seperti sisa `30` yang seharusnya `20` ikut terkoreksi saat label diakses kembali.
- **Regression Coverage:** Penambahan coverage khusus memastikan PDF label sisa sheet menggunakan nilai hasil rekonsiliasi dan menyimpan koreksi tersebut ke database tanpa mengganggu alur delivery maupun BA penyerahan yang sudah diperbaiki sebelumnya.
- **Production Deployment:** Perbaikan label sisa ini telah dipaketkan dan dideploy ke production melalui artifact deploy, lalu diverifikasi pada request `242` bahwa `delivered=30`, `tested=10`, dan `stored_remaining=20` sudah konsisten.

### v2.5.3 (15 Juni 2026) - Delivery, Statistics, Google Drive & WhatsApp Hardening

- **Delivery Processing Time:** Dashboard penyerahan sekarang menampilkan durasi proses pengujian dari penerimaan hingga siap serah, sehingga petugas dapat memantau waktu layanan langsung dari daftar delivery.
- **Investigator Edit Hardening:** Edit data penyidik dari modul delivery diperkuat dengan route, validasi, dan tampilan yang lebih aman agar koreksi informasi penyidik tidak merusak konteks penyerahan yang sedang berjalan.
- **Statistics Report Guard:** Preview consolidated report dilindungi dari kondisi data kosong/tidak lengkap, dan laporan periodik kini dapat menyertakan appendix dashboard untuk memperkaya ringkasan statistik operasional.
- **Dependency Audit Remediation:** Lockfile NPM diperbarui untuk menutup temuan audit dependency yang dapat diselesaikan tanpa perubahan perilaku aplikasi.
- **Google Drive Sync Health:** Pemeriksaan kesehatan sinkronisasi Google Drive diperkuat melalui command health, error handling controller/profile, dan guard sync dokumen agar status koneksi lebih mudah didiagnosis sebelum upload dokumen operasional.
- **Remaining Quantity Reconciliation:** Alur penyerahan dan dokumen QMH pendukung diperbaiki agar rekonsiliasi jumlah sisa sampel lebih konsisten antara tampilan delivery, PDF BA Penyerahan, picker dokumen pendukung, dan halaman QMH.
- **Handover PDF Code Alignment:** Footer dan kode dokumen pada PDF BA Penyerahan diselaraskan dengan format dokumen yang berlaku, dilengkapi coverage untuk format penandatangan dan kode dokumen.
- **GOWA Settings Save Fix:** Tab GOWA di WhatsApp Hub sekarang menyimpan konfigurasi GOWA tanpa ikut mengirim field konfigurasi AI, sehingga validasi AI tidak lagi dapat menggagalkan penyimpanan `Base URL`, `Device ID`, `Basic Auth User`, dan `Basic Auth Password`.
- **AI Model Options Update:** Pilihan model pada tab AI diperbarui menjadi `gpt-5.3-codex-spark`, `gpt-oss-120b-medium`, `gpt-5.4-mini`, `gpt-5.5`, `gpt-5.4`, dan `gpt-image-2`, dengan placeholder yang diselaraskan ke opsi default terbaru.
- **Regression Coverage:** Coverage feature diperluas untuk delivery progress, edit penyidik delivery, consolidated report, Google Drive sync, label sisa/BA penyerahan, QMH pendukung, dan WhatsApp settings agar perubahan setelah rilis 6 Mei tetap terlindungi.
- **Production Deployment:** Perubahan WhatsApp telah dipaketkan melalui artifact deploy production, menjalankan composer install, migration check, Vite build, optimize cache, serta verifikasi pascadeploy `about`, `migrate:status`, dan smoke read-only QMH.

### v2.5.2 (6 Mei 2026) - Production Artifact Cleanup & Seeder Safety

- **Production Artifact Cleanup:** Seeder khusus pengujian lokal, dummy data, dan command bootstrap demo lokal dihapus dari artifact production agar paket deploy hanya membawa kode operasional yang relevan.
- **Seeder Safety:** `DatabaseSeeder` sekarang hanya menjalankan seeder konfigurasi sistem yang dibutuhkan, sehingga perintah seeding default tidak lagi dapat membuat user admin dev/test atau data dummy perkara, sampel, label, pengujian, maupun pemusnahan.
- **Deploy Hygiene:** Deploy production tetap menggunakan artifact dari clean `HEAD`, tanpa stash atau file uncommitted, dengan verifikasi bahwa file seeder user/dummy tidak tersedia lagi pada target production.
- **Regression Coverage:** Full Pest parallel, Pint, dan audit critical dijalankan ulang setelah cleanup untuk memastikan penghapusan seeders dev-only tidak mengganggu alur aplikasi yang berjalan.

### v2.5.1 (2 Mei 2026) - Google Drive Sync, Intake Simplification & Delivery Label Reliability

- **Integrasi Google Drive:** Sistem sekarang mendukung koneksi OAuth Google Drive per user, pengaturan uploader terpusat, folder tujuan configurable, dan upload dokumen melalui Google Drive REST API v3 dengan metadata sinkronisasi tersimpan pada record dokumen.
- **Folder Drive Operasional:** Dokumen tersinkron ke struktur proses yang lebih rapi (`Permintaan`, `Pengujian`, `Penyerahan`) di bawah folder request berbasis bulan, nomor resi, dan nama tersangka sehingga arsip digital lebih mudah ditelusuri tanpa mengubah local storage sebagai sumber data utama.
- **Sync Resilience:** Kegagalan upload Drive tidak lagi menggagalkan penyimpanan lokal; status `uploaded`, `skipped`, atau `failed` dicatat pada metadata dokumen agar user tetap bisa lanjut bekerja dan admin dapat menelusuri kondisi sinkronisasi.
- **Request Intake Revision:** Halaman `/requests/create` dan edit permintaan disederhanakan menjadi fokus administrasi penerimaan, data penyidik, surat permintaan, tersangka, dokumen, dan fisik sampel; input teknis seperti zat aktif serta jenis/metode pengujian dipindahkan ke alur Kaji Ulang.
- **Saksi Ahli Support:** Permintaan saksi ahli sekarang memiliki nomor surat, tanggal surat, dan upload PDF tersendiri dengan validasi kondisional saat opsi saksi ahli dipilih.
- **BA Penerimaan Cleanup:** Berita Acara Penerimaan diperbarui agar hanya memuat informasi penerimaan yang relevan dan tidak lagi menampilkan field teknis seperti zat aktif, jenis pengujian, atau tujuan kantor.
- **Penyerahan Detail Update:** Halaman `/delivery/{request}` kini menampilkan detail dokumen permintaan, nomor/tanggal surat, informasi saksi ahli bila ada, serta link LHU per sampel saat tersedia untuk membantu verifikasi sebelum penyerahan.
- **Label Sisa Reliability:** PDF label sisa single dan sheet diperbaiki agar data `RESI`, `KODE`, `QTY SISA`, `SEGEL`, dan QR code selalu ter-render jelas di Chrome/PDF viewer maupun file yang disimpan/disinkronkan.
- **Label Document Sync:** Cetak label barang bukti dan label sisa sekarang menyimpan dokumen generated yang sama dengan binary PDF yang dikirim ke browser, sehingga file lokal/Drive tidak berbeda dari preview yang dilihat user.
- **Numbering Hardening:** Penerbitan nomor BA dan resi diperkuat dengan fallback format per-scope serta skip nomor yang sudah ada, sehingga sequence lokal yang tertinggal tidak lagi menyebabkan submit permintaan kembali ke form karena duplikasi nomor.
- **Regression Coverage:** Penambahan dan pembaruan test feature untuk request store/update, BA Penerimaan, upload dokumen, LHU, penyerahan, evidence label, remaining label, dan safety numbering memastikan perubahan lintas modul tetap terjaga.

### v2.5.0 (20 April 2026) - Evidence Label Sheet Packing, Safety Hardening & Request Form UX

- **Evidence Sheet Repack:** Layout cetak `labels/evidence-sheet` sekarang memisahkan halaman pertama sebagai format campuran (barang bukti + label `LPMF`) dan halaman lanjutan sebagai grid `2 x 4`, sehingga halaman kedua dan seterusnya dapat memuat hingga **8 label barang bukti** dengan jarak horizontal dan vertikal **5mm** yang lebih konsisten di DomPDF.
- **Label Visual Alignment:** Tipografi label barang bukti diselaraskan dengan profil label `LPMF`, termasuk spacing internal yang lebih rapat dan aman untuk mengurangi risiko overflow serta menjaga keterbacaan pada ukuran label fisik saat dicetak.
- **Evidence Label Sync on Print:** Proses cetak label barang bukti sekarang otomatis memastikan semua sampel pada request sudah memiliki `evidence_units` sebelum PDF dibangun, sehingga kasus sampel tambahan yang sebelumnya belum memiliki label tidak lagi hilang dari lembar cetak.
- **Case Label Summary Hardening:** Ringkasan `Nomor Sampel` pada label `LPMF` kini hanya dikompakkan ke format range bila semua kode memiliki prefix yang sama dan urutan numeriknya benar-benar kontigu; jika tidak, sistem fallback aman ke daftar pendek atau format `N sampel` untuk mencegah informasi yang menyesatkan.
- **Regression Coverage:** Penambahan `EvidenceSheetPrintTest` memperluas coverage untuk sinkronisasi label otomatis, packing halaman lanjutan, dan guard terhadap kompaksi kode sampel yang tidak valid agar kontrak layout cetak tetap terjaga saat ada perubahan berikutnya.
- **Request Form UX:** Field `Deskripsi Singkat` pada `/requests/create` sekarang menggunakan `textarea` full-width dengan auto-resize di setiap card sampel, sehingga deskripsi lebih dari 10 karakter tetap mudah dibaca, nyaman diedit, dan konsisten juga untuk sampel yang ditambahkan secara dinamis.
- **Deploy Config Update:** Konfigurasi deploy lokal diperbarui untuk memakai host production baru `192.168.1.16`, dan artifact deploy untuk perubahan label ini sudah tervalidasi berjalan di target production tersebut.

### v2.4.12 (14 April 2026) - Disposal Monthly Batch Flow & UI Redesign

- **Monthly Disposal Execution:** Halaman `/referensi/inventori/disposal` sekarang menyediakan grouping bulanan berbasis interpretasi terakhir per sampel, sehingga user dapat mengeksekusi disposal per bulan dengan hasil yang konsisten antara dashboard eligible dan form batch.
- **Eligibility Query Hardening:** Penyusunan ringkasan bulanan disposal dipindahkan dari snapshot penuh in-memory ke query agregasi `latest interpretation` per sampel agar lebih hemat memory dan lebih aman saat jumlah data production bertambah.
- **Disposal Create Redesign:** Form eksekusi batch disposal diperbarui menjadi layout command-center dengan ringkasan batch, disclosure section untuk pelaksana/saksi/otorisasi/dokumentasi, serta auto-open saat ada validation error agar recovery form tetap jelas.
- **Disposal Detail Redesign:** Halaman detail batch disposal kini menampilkan hero ringkasan audit, metadata batch yang lebih mudah dipindai, panel saksi dan dokumentasi yang lebih rapi, serta manifest sampel dengan presentasi yang lebih konsisten untuk kebutuhan telaah dan cetak PDF.

### v2.4.11 (10 April 2026) - Disposal Documentation Photos & Report Evidence Column

- **Disposal Documentation Upload:** Batch pemusnahan sekarang mendukung upload hingga **5 foto dokumentasi** langsung dari form eksekusi, dengan validasi tipe gambar dan batas ukuran per file.
- **Operational Evidence Visibility:** Halaman detail pemusnahan kini menampilkan galeri dokumentasi yang bisa dibuka langsung tanpa bergantung pada `storage:link`, sehingga lebih aman untuk environment production saat ini.
- **Report / PDF Update:** Berita Acara Pemusnahan sekarang memiliki kolom **Dokumentasi** pada tabel laporan dan lampiran section **Dokumentasi Pemusnahan** saat foto tersedia.
- **Failure Cleanup Hardening:** File dokumentasi yang sudah terupload akan dibersihkan kembali jika transaksi pemusnahan gagal, untuk mencegah orphan file di storage.

### v2.4.10 (1 April 2026) - Configurable Disposal Retention Window

- **Configurable Retention:** Rule umur disposal sekarang memakai setting `inventory.disposal_retention_days` dengan fallback aman `90`, sehingga kebijakan retensi dapat diubah tanpa patch code ulang.
- **Operational Flexibility:** Nilai setting `0` sekarang mengizinkan disposal segera setelah `interpretation` selesai dan `lhu_number` tersedia, sementara nilai `> 0` tetap menegakkan masa retensi sesuai kebutuhan operasional.
- **Test Coverage:** Coverage disposal diperluas untuk memastikan fallback default `90` tetap aktif saat setting belum diisi dan behavior `0 hari` berjalan sesuai konfigurasi.

### v2.4.9 (1 April 2026) - Disposal Eligibility Production Fix & Access Hardening

- **Disposal Eligibility Fix:** Halaman `/referensi/inventori/disposal` sekarang mendeteksi sampel siap musnah dari data riil workflow (`interpretation` selesai, `lhu_number` ada, umur hasil uji >= 90 hari) sehingga data production yang sebelumnya tertahan di status `pending` tetap muncul sebagai eligible.
- **Execution Safety:** Eksekusi batch disposal sekarang me-recheck eligibility di dalam transaksi database dan menambahkan retry untuk benturan `batch_number` agar lebih aman saat ada request paralel.
- **Access Hardening:** Route disposal sekarang dibatasi dengan permission inventori, dan tombol/link akses disposal di dashboard hanya tampil untuk user yang memang punya hak lihat inventori.
- **Form UX Fix:** Input saksi manual sekarang sinkron dengan validasi backend, sehingga user tidak lagi dipaksa memilih saksi dari daftar user sistem.

### v2.4.8 (12 Maret 2026) - Landing Page Institusional & Statistik Real-time

- **Landing Page Redesign:** Halaman publik `/` diperbarui menjadi landing page bergaya editorial-institusional dengan navigasi yang lebih bersih, fokus pada transparansi layanan, dan identitas visual LPMF LIMS yang konsisten.
- **Branding Alignment:** Logo pojok kiri atas kini menggunakan aset PNG resmi Pusdokkes Polri, dan penamaan brand diseragamkan agar selalu tampil sebagai **LPMF LIMS**.
- **Real Operational Data:** Ringkasan operasional landing page kini mengambil data langsung dari database (resi aktif, sampel terdaftar, rata-rata proses, SLA, resi selesai, dan user aktif), bukan angka placeholder.
- **Footer Version Sync:** Versi pada footer landing page sekarang mengikuti entri changelog terbaru dari `WALKTHROUGH.md` agar sinkron dengan riwayat rilis.
- **Copy Refresh:** Copy publik diperhalus untuk menghapus diksi yang tidak diperlukan dan memperjelas instruksi pelacakan menjadi fokus pada **nomor resi**.

### v2.4.7 (4 Maret 2026) - Personnel Role Management & Deploy Stability

- **Personnel Role CRUD:** Halaman kelola role kini mendukung edit dan hapus role secara langsung, mengurangi kebutuhan intervensi manual di database.
- **Changelog Deploy Fix:** File `WALKTHROUGH.md` sekarang disertakan dalam artifact deploy sehingga halaman `/changelogs` tampil dengan benar di production.
- **Dependency Housekeeping:** Upgrade LHCI dan Puppeteer untuk menghilangkan warning deprecated, serta keluarkan LHCI dari dependency path deploy agar build lebih ringan.

### v2.4.6 (1 Maret 2026) - QMH Template & PDF Polish, Statistics Resend

- **QMH Watermark Underlay:** Watermark FR kini dirender sebagai underlay (bukan overlay) agar konten dokumen tetap mudah dibaca, termasuk stabilisasi path proof dan fallback store.
- **Template Management Cleanup:** Surface manajemen template dihapus dari UI untuk menghindari konflik alur; output PDF dipoles lebih konsisten.
- **FR Dashboard Split:** Dashboard FR kini dipisah antara varian tabel dan non-tabel, masing-masing menampilkan satu template referensi per kartu agar navigasi lebih jelas.
- **Template Editor Overhaul:** Editor template QMH dimigrasikan ke plain code editor dengan alur blade-template workflow, termasuk preview hash semantics yang lebih trustworthy dan status header/footer pada kartu template.
- **Statistics Report Resend:** Notifikasi laporan statistik kini bisa dikirim ulang secara manual dari halaman laporan.
- **Deploy Hardening:** Proses cleanup artifact deploy diperketat dan file PNG temporer lokal dikecualikan untuk menghindari gangguan build.

### v2.4.5 (28 Februari 2026) - QMH Download Finalization & FR-v2 PDF Consistency

- **QMH Download Naming Final:** Format nama file unduhan QMH diseragamkan untuk semua tipe dokumen. FR kini eksplisit menyertakan varian `TABEL`/`NON TABEL`, versi, dan label distribusi `TERKENDALI`/`TIDAK TERKENDALI`; SOP/IK mengikuti format final tanpa suffix varian FR.
- **FR-v2 Declaration Render Stability:** Pipeline PDF FR-v2 (FPDI) diperketat agar declaration versi minimal tetap konsisten menampilkan elemen header/footer penting, termasuk konteks status dokumen pada area header yang relevan.
- **Branding Path Hardening:** Resolusi logo pada layout utama/guest dinormalisasi menggunakan helper aset agar path branding lebih stabil lintas environment.
- **Regression Safety Net:** Cakupan uji unduhan QMH diperluas (FR tabel, FR non-tabel, SOP, IK) untuk memastikan kontrak filename dan output PDF tetap konsisten setelah perubahan.

### v2.4.4 (22 Februari 2026) - QMH Governance Suite v2 (Rapat, Audit, KUM)

- **Governance Workspace:** Halaman baru `/quality/governance` sebagai ringkasan lintas modul (Rapat, Audit, KUM) dengan metrik due-soon/overdue dan quick actions operasional.
- **Audit Assignment Refactor:** Assignment auditor Audit QMH dimigrasikan dari `auditors_json` ke pivot `qmh_audit_auditors` untuk queryability, visibility rule, dan relasi yang lebih stabil jangka panjang.
- **Action Item State Machine:** Transisi status action item rapat sekarang divalidasi oleh service state machine + middleware transition guard, termasuk automation overdue terjadwal (`qmh:action-items:refresh-overdue`).
- **Audit Trail Hardening:** Penambahan `audit_trails` + `AuditTrailService` untuk pencatatan perubahan governance (create/update/state change) dengan metadata actor/source/request.
- **KUM Follow-Up Generator:** Keputusan KUM sekarang bisa langsung menghasilkan action item terstruktur (web + API) dengan validasi assignee governance dan due date.
- **API Extensibility:** Endpoint baru untuk summary governance dan action item lifecycle/dependencies (`/api/quality/governance/summary`, `/api/quality/action-items/*`) termasuk validasi circular dependency.
- **Production Safety:** Permission governance transisi action item disiapkan via migration sinkronisasi production, dan `PermissionSeeder` sekarang fail-closed di environment production.

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
