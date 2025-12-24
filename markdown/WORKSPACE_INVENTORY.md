# WORKSPACE INVENTORY

## 1) Ringkasan Proyek
- Tujuan aplikasi & peran utamanya
  - Aplikasi internal untuk pengelolaan permintaan pengujian laboratorium (Lidik/Sidik) di Farmapol Pusdokkes Polri: pencatatan permintaan (TestRequest), manajemen sampel (Sample), proses pengujian bertahap (SampleTestProcess), pembuatan dokumen/berita acara/pelaporan (Document), penyerahan hasil (Delivery), tracking publik, statistik, dan pengaturan sistem.
  - Fitur utama tampak dari routes dan model: Requests CRUD, pengujian sampel, dokumen Berita Acara, tracking, statistik, delivery/handover, investigator documents, serta halaman admin (settings, analysts).

- Arsitektur singkat
  - Monolith Laravel 12 (SSR Blade) dengan Vite untuk aset FE; interaktivitas ringan menggunakan Alpine.js. Tidak terlihat SPA/router khusus FE.
  - Komponen utama: Laravel MVC + TailwindCSS + DomPDF untuk PDF + Spatie Permission untuk kontrol peran/izin.

- Bahasa & runtime
  - PHP ^8.2 (lihat `composer.json` require.php). Bukti: `composer.json` -> "php": "^8.2".
  - Node.js tooling (Vite 7, Tailwind 3). Bukti: `package.json` devDependencies "vite": "^7.0.4", "tailwindcss": "^3.4.18".

## 2) Backend
- Framework & versi
  - Laravel Framework: ^12.0. Bukti: `composer.json` -> "laravel/framework": "^12.0".
  - Paket penting: barryvdh/laravel-dompdf ^3.1 (PDF), spatie/laravel-permission ^6.21 (roles/permissions), simple-qrcode ^4.2, laravel/tinker.

- Struktur folder backend
  - `app/`
    - `Console/` (Commands/, Kernel.php tidak ditemukan—kemungkinan belum dibuat atau diabaikan. Scheduler default tidak terlihat.)
    - `Enums/`, `Events/`, `Listeners/` (ada, isi rinci tidak ditinjau penuh)
    - `Http/` (Controllers, Middleware, Requests). Contoh FormRequest: `app/Http/Requests/ProfileUpdateRequest.php`.
    - `Models/` (AuditLog, Delivery, DeliveryItem, Document, DocumentTemplate, Investigator, Organization, Request, Sample, SampleTestProcess, Sequence, SurveyResponse, SystemSetting, TestRequest, TestResult, User).
    - `Policies/` (InvestigatorDocumentPolicy.php)
    - `Providers/` (AppServiceProvider.php)
    - `Services/`, `Support/`, `View/` (tersedia)
    - Helpers: `app/helpers.php`, `app/Support/helpers.php` di-autoload.
  - `config/` (auth, cache, database, dompdf, filesystems, logging, mail, permission, queue, services, session, app)
  - `database/` (migrations, factories, seeders)
  - `routes/` (web.php, api.php, auth.php, console.php)

- Service Provider / Middleware / Policy / Gate / Job / Event / Listener / Scheduler
  - Service Provider: `app/Providers/AppServiceProvider.php` (isi tidak dibaca detail).
  - Middleware: `app/Http/Middleware/ApplyLocaleFromSettings.php` (aplikasi locale dari pengaturan—detail implementasi tidak dibaca).
  - Policy: `app/Policies/InvestigatorDocumentPolicy.php` (policy terkait dokumen investigator).
  - Gates: tidak terdeteksi eksplisit; pada routes ada middleware `can:manage-settings` (mengandalkan Spatie Permission/Gate).
  - Jobs/Queue: tidak terlihat kelas Job khusus; antrian dikonfigurasi di `config/queue.php` (default `database`).
  - Events/Listeners: folder tersedia, spesifik tidak dipetakan dalam audit ini.
  - Scheduler: `routes/console.php` hanya command `inspire`; `app/Console/Kernel.php` tidak ada (scheduler custom tidak terdeteksi).

- Model & Relasi penting
  - TestRequest
    - Relasi: `belongsTo(Investigator)`, `belongsTo(User)`, `hasMany(Sample)`, `hasMany(Document)`.
    - Penomoran otomatis `request_number` saat creating (lihat `app/Models/TestRequest.php` lines static::creating & generateRequestNumber). Cache key yang dibersihkan: `track:condensed:<request_number>`.
    - Kolom inti (migrasi `2025_09_29_03_create_test_requests_table.php`): id, request_number (unique), investigator_id (FK), user_id (FK), status enum [submitted..completed], timestamps seperti submitted_at/verified_at/received_at/completed_at, dll.
  - Sample
    - Relasi: `belongsTo(TestRequest)`, `hasOne(TestResult)`, `hasMany(SampleTestProcess)`, `belongsTo(User, 'assigned_analyst_id')`.
    - Penomoran sample_code W<number><ROMAN_MONTH><year> via transaksi DB (lihat `app/Models/Sample.php` -> generateSampleCode()).
    - Kolom inti (migrasi `2025_09_29_044652_create_samples_table.php`): sample_code unique, test_request_id FK, sample_form/category enums, test_methods text (opsional JSON), active_substance, status progres pengujian, timestamps.
  - SampleTestProcess
    - Relasi: `belongsTo(Sample)`, `belongsTo(User, 'performed_by')`.
    - Cast enum `stage` ke `App\Enums\TestProcessStage`; metadata JSON.
    - Migrasi `2025_10_02_152107_create_sample_test_processes_table.php`: unique [sample_id, stage]; kolom started/completed, notes, metadata.
  - Document
    - Migrasi `2025_09_29_044653_create_documents_table.php`: test_request_id FK, `document_type` enum [lab_report, cover_letter, handover_report, sample_receipt, report_receipt, letter_receipt, sample_handover, test_results, qr_code], path & metadata file, generated_by FK users, timestamps.
  - Lainnya: Delivery/DeliveryItem, Investigator, SystemSetting, TestResult, User—detail relasi tidak seluruhnya dikaji tetapi digunakan oleh routes/controller.

- Validasi & Form Request signifikan
  - `ProfileUpdateRequest` memvalidasi name required dan email unique (Rule::unique(User::class)->ignore(current user)). Bukti: `app/Http/Requests/ProfileUpdateRequest.php` lines 15-31.

- Konfigurasi queue/cache/session/storage
  - Queue: default `database` (env QUEUE_CONNECTION). Driver lain tersedia (sync, beanstalkd, sqs, redis). Batching: database (job_batches). Failed jobs: `database-uuids`. Bukti: `config/queue.php` lines 'default', 'connections'.
  - Cache: default `database` (env CACHE_STORE). Stores: array/database/file/memcached/redis/dynamodb/octane. Prefix dari APP_NAME. Bukti: `config/cache.php` lines 'default','stores','prefix'.
  - Session: default `database` (env SESSION_DRIVER). Table default `sessions`. Cookie name dari APP_NAME. Bukti: `config/session.php` 'driver','table','cookie'.
  - Filesystem: default `local`. Disks tambahan: public, documents, test_results, samples (public), official_docs, generated_docs, lab_images, archives, s3. Symlink: public/storage dan public/storage/samples. Bukti: `config/filesystems.php`.

## 3) Routes Backend
- Sumber: `routes/web.php`, `routes/api.php`, `routes/auth.php`, `routes/console.php`.

- Route Web & API (ringkasan tabel)

| Method | Path | Name | Controller@action | Middleware |
|---|---|---|---|---|
| POST | /locale/{locale} | locale.switch | LocaleController@switch | - |
| GET | / | - | view('landing') or redirect('dashboard') | - |
| GET | /track | public.tracking | TrackingController@index | - |
| POST | /track | public.track | TrackingController@store | - |
| GET | /track/{tracking_number}.json | public.tracking.json | TrackingController@json | - |
| GET | /dashboard | dashboard | DashboardController@index | auth, verified |
| GET | /api/dashboard-stats | dashboard.stats | DashboardController@getStats | auth, verified |
| GET | /profile | profile.edit | ProfileController@edit | auth, verified |
| PATCH | /profile | profile.update | ProfileController@update | auth, verified |
| DELETE | /profile | profile.destroy | ProfileController@destroy | auth, verified |
| resource | /requests | requests.* | RequestController | auth, verified |
| GET | /requests/{request}/berita-acara/check | requests.berita-acara.check | RequestController@checkBeritaAcara | auth, verified |
| POST | /requests/{request}/berita-acara/generate | requests.berita-acara.generate | RequestController@generateBeritaAcara | auth, verified |
| GET | /requests/{request}/berita-acara/download | requests.berita-acara.download | RequestController@downloadBeritaAcara | auth, verified |
| GET | /requests/{request}/berita-acara/view | requests.berita-acara.view | RequestController@viewBeritaAcara | auth, verified |
| GET | /samples/test | samples.test.create | SampleTestController@create | auth, verified |
| POST | /samples/test | samples.test.store | SampleTestController@store | auth, verified |
| GET | /samples/test/{sampleDetail} | samples.test.show | SampleTestController@show | auth, verified |
| GET | /samples | samples.index | redirect()->route('samples.test.create') | auth, verified |
| resource | /sample-processes | sample-processes.* | SampleTestProcessController | auth, verified |
| GET | /sample-processes/{id}/form/{stage} | sample-processes.generate-form | SampleTestProcessController@generateForm | auth, verified |
| GET | /sample-processes/{id}/lab-report | sample-processes.lab-report | SampleTestProcessController@generateReport | auth, verified |
| POST | /samples/{sample}/ready-for-delivery | samples.ready-for-delivery | SampleTestProcessController@markAsReadyForDelivery | auth, verified |
| resource except show | /analysts | analysts.* | AnalystController | auth, verified |
| GET | /delivery | delivery.index | DeliveryController@index | auth, verified |
| GET | /delivery/{request} | delivery.show | DeliveryController@show | auth, verified |
| POST | /delivery/{request}/complete | delivery.complete | DeliveryController@markAsCompleted | auth, verified |
| POST | /delivery/{delivery}/handover/generate | delivery.handover.generate | DeliveryController@handoverGenerate | auth, verified |
| GET | /delivery/{delivery}/handover/view | delivery.handover.view | DeliveryController@handoverView | auth, verified |
| GET | /delivery/{delivery}/handover/download | delivery.handover.download | DeliveryController@handoverDownload | auth, verified |
| GET | /delivery/{request}/survey | delivery.survey | DeliveryController@surveyForm | auth, verified |
| POST | /delivery/{request}/survey | delivery.survey.submit | DeliveryController@submitSurvey | auth, verified |
| GET | /tracking | tracking.index | TrackingController@index | auth, verified |
| POST | /tracking | tracking.store | TrackingController@store | auth, verified |
| GET | /statistics | statistics.index | StatisticsController@index | auth, verified |
| GET | /statistics/data | statistics.data | StatisticsController@data | auth, verified |
| GET | /statistics/export | statistics.export | StatisticsController@export | auth, verified |
| GET | /settings | settings.index | SettingsPageController@index | auth, verified, can:manage-settings |
| GET | /settings/data | settings.show | SettingsController@show | auth, verified, can:manage-settings |
| POST | /settings/save | settings.update | SettingsController@update | auth, verified, can:manage-settings |
| POST | /settings/preview | settings.preview | SettingsController@preview | auth, verified, can:manage-settings |
| POST | /settings/test | settings.test | SettingsController@test | auth, verified, can:manage-settings |
| POST | /settings/brand-asset | settings.brand.upload | SettingsController@uploadBrandAsset | auth, verified, can:manage-settings |
| GET | /settings/templates | settings.templates.index | TemplateController@index | auth, verified, can:manage-settings |
| POST | /settings/templates | settings.templates.store | TemplateController@store | auth, verified, can:manage-settings |
| POST | /settings/templates/activate | settings.templates.activate | TemplateController@activate | auth, verified, can:manage-settings |
| POST | /numbering/{scope}/preview | numbering.preview | NumberingController@preview | auth, verified |
| POST | /numbering/{scope}/issue | numbering.issue | NumberingController@issue | auth, verified |
| GET | /database | database.index | DatabaseController@index | auth, verified |
| GET | /database/suggest | database.suggest | DatabaseController@suggest | auth, verified |
| GET | /database/docs/generated/download | database.docs.download.generated | DatabaseController@download | signed |
| GET | /database/docs/generated/preview | database.docs.preview.generated | DatabaseController@preview | signed |
| GET | /database/docs/{doc}/download | database.docs.download | DatabaseController@download | signed |
| GET | /database/docs/{doc}/preview | database.docs.preview | DatabaseController@preview | signed |
| GET | /database/request/{testRequest}/bundle | database.request.bundle | DatabaseController@bundle | auth, verified |
| GET | /investigators/{investigator}/documents | investigator.documents.index | InvestigatorDocumentController@index | auth, verified |
| GET | /investigators/{investigator}/documents/create | investigator.documents.create | InvestigatorDocumentController@create | auth, verified |
| POST | /investigators/{investigator}/documents | investigator.documents.store | InvestigatorDocumentController@store | auth, verified |
| GET | /documents/{document} | investigator.documents.show | InvestigatorDocumentController@show | auth, verified |
| GET | /documents/{document}/download | investigator.documents.download | InvestigatorDocumentController@download | signed |
| DELETE | /documents/{document} | investigator.documents.destroy | InvestigatorDocumentController@destroy | auth, verified |
| GET | /debug/doc-probe | debug.doc-probe | DebugDocController@probe | - |
| GET | /debug/file-upload | debug.file-upload | return file(public/debug-file-upload.html) | - |
| MATCH GET,POST | /debug/file-keys | debug.file-keys | closure (returns JSON of file keys) | - |
| GET | /debug/ba/{id} | debug.ba | closure -> RequestController@generateBeritaAcara | auth |
| GET | /debug/process/{id} | debug.process | closure uses DocumentService | auth |
| VIEW | /design-examples | design.examples | design-examples view | auth, verified |
| GET | /register | register | RegisteredUserController@create | guest |
| POST | /register | - | RegisteredUserController@store | guest |
| GET | /login | login | AuthenticatedSessionController@create | guest |
| POST | /login | - | AuthenticatedSessionController@store | guest |
| GET | /forgot-password | password.request | PasswordResetLinkController@create | guest |
| POST | /forgot-password | password.email | PasswordResetLinkController@store | guest |
| GET | /reset-password/{token} | password.reset | NewPasswordController@create | guest |
| POST | /reset-password | password.store | NewPasswordController@store | guest |
| GET | /verify-email | verification.notice | EmailVerificationPromptController | auth |
| GET | /verify-email/{id}/{hash} | verification.verify | VerifyEmailController | signed, throttle:6,1 |
| POST | /email/verification-notification | verification.send | EmailVerificationNotificationController@store | throttle:6,1 |
| GET | /confirm-password | password.confirm | ConfirmablePasswordController@show | auth |
| POST | /confirm-password | - | ConfirmablePasswordController@store | auth |
| PUT | /password | password.update | PasswordController@update | auth |
| POST | /logout | logout | AuthenticatedSessionController@destroy | auth |
| GET | /api/requests/{requestNumber} | - | closure returning BA JSON (api.php) | - |
| GET | /api/sample-processes/{processId} | - | closure returning LHU JSON (api.php) | - |

- Endpoint health/diagnostic
  - Tidak ada endpoint health spesifik. Ada debug endpoints: `/debug/doc-probe`, `/debug/file-keys`, `/debug/ba/{id}`, `/debug/process/{id}` (sebagian ber-middleware auth, sebagian tidak). Saran: lindungi semua debug route dengan `auth`/`env('APP_DEBUG')` gate untuk produksi.

## 4) Database & Migrasi
- Ringkasan skema utama (berdasarkan migrations):
  - users (file ada: `2025_09_29_01_create_users_table.php`) – tidak dirinci, serta sessions, password_reset_tokens, failed_jobs, cache.
  - investigators (`2025_09_29_02_create_investigators_table.php`) – data penyidik; update email nullable (`2025_10_05_170953...`).
  - test_requests (lihat di atas)
  - samples (lihat di atas; `2025_10_01_120000_add_testing_fields_to_samples_table.php` menambah kolom terkait pengujian)
  - documents (lihat di atas; beberapa migrasi perubahan constraint document_type pada 2025-10-01..10-10)
  - test_results (`2025_09_29_044653_create_test_results_table.php`) – tidak dibaca rinci
  - survey_responses (`2025_09_29_044654_create_survey_responses_table.php`)
  - sample_test_processes (`2025_10_02_152107_create_sample_test_processes_table.php`)
  - system_settings tables (`2025_10_09_000000_create_system_settings_tables.php`)
  - deliveries & delivery_items (`2025_10_15_000001`, `...000002`) – penyerahan hasil
  - workflow status columns (`2025_10_16_000001_add_workflow_status_columns.php`)

- Kunci penting: mayoritas tabel memiliki timestamps. Foreign keys pada test_requests, samples, documents, sample_test_processes. Beberapa enum status untuk workflow.
- Status migrasi/seeders: factories/seeders ada; tidak ada informasi jalan/terkini dalam audit file.

## 5) Frontend
- Framework & router
  - Blade templating (SSR). Komponen Blade di `resources/views/components`. Layouts di `resources/views/layouts`.
  - Interaktivitas: Alpine.js (package.json devDependency, `resources/js/app.js` inisialisasi Alpine, data components listFetcher, dashboardStats, theme switcher).
  - Router FE: tidak ada React/Vue router. Navigasi via Blade routes.

- Struktur src
  - `resources/js/app.js` (entry; mengimpor `./bootstrap`, Alpine, util list-fetcher, mengelola tema, dan komponen dashboard stats/polling `/api/dashboard-stats`).
  - `resources/js/bootstrap.js` (tidak dibaca, biasanya setup axios/csrf).
  - `resources/js/utils/list-fetcher.js` (helper untuk fetch list—tidak dibaca rinci).
  - `resources/views/...` halaman: dashboard, landing, tracking, requests, samples, sample-processes, delivery, settings, analysts, pdf templates, auth, profile, database, statistics, dll.

- Pemetaan halaman/route FE
  - Dari `resources/views/layouts/navigation.blade.php`, tautan utama: Dashboard, Permintaan (requests.*), Pengujian (samples.*), Proses (sample-processes.*), Penyerahan (delivery.*), Referensi (Tracking/Database/Statistik), Admin (Analysts, Pengaturan Sistem). Guard role berbasis `Auth::user()->role` arrays `labRoles` dan `supervisorRoles`. Bukti: `resources/views/layouts/navigation.blade.php` lines 24-63, 88-159, dsb.

- State management, i18n, forms, chart libs
  - Tidak ada Redux/Pinia; Alpine state lokal. i18n tidak terdeteksi (Laravel locale switching via POST /locale/{locale}). Charts tidak terdeteksi.

- Build tooling
  - Vite 7 dengan `laravel-vite-plugin`. Entry: `resources/css/app.css`, `resources/js/app.js`, dan `resources/css/ui-scope.css`. Bukti: `vite.config.js`.
  - TailwindCSS 3, plugin @tailwindcss/forms, PostCSS (tailwindcss, autoprefixer). Bukti: `tailwind.config.js`, `postcss.config.js`.

## 6) Resources & Aset
- Views/Blade/Components
  - Layouts: `resources/views/layouts/{app,guest,navigation}.blade.php`.
  - Components: banyak komponen utilitas UI seperti `components/nav-link.blade.php`, `components/page-header.blade.php`, `components/kpi-card.blade.php`, dll.
  - Halaman: dashboard, landing, requests, samples, sample-processes, deliveries, settings, statistics, tracking, auth, profile, database, pdf.

- Style system
  - TailwindCSS dengan layer components men-define util seperti `.btn`, `.badge`, `.nav-link`, dsb. Bukti: `resources/css/app.css`.
  - Design token semantik tersedia via `theme-semantic.css` dan `theme-dark.css`; contoh `design-tokens.example.json` tersedia.
  - Tailwind config memuat palet warna untuk medical brand (primary/secondary/accent/semantic) dan utilities custom. Bukti: `tailwind.config.js`.

- Asset penting
  - Logo: digunakan oleh navbar `src="/images/logo-pusdokkes-polri.png"` (pastikan tersedia di `public/images`). Aturan import assets via Vite untuk CSS/JS; images via public/.

## 7) Integrasi & Layanan Eksternal
- Email: mailers (smtp/ses/postmark/resend/log/array/failover/roundrobin). ENV: MAIL_HOST/PORT/USERNAME/PASSWORD/…; default mailer `log`. Bukti: `config/mail.php`.
- AWS SES/S3: `config/services.php` (ses) dan `config/filesystems.php` (s3) dengan ENV `AWS_*`.
- Slack notifications (bot token & channel). Bukti: `config/services.php` -> 'slack.notifications'.
- WhatsApp API (base_url, api_key). Bukti: `config/services.php` -> 'whatsapp'.
- DomPDF konfigurasi ketat (chroot base_path, enable_remote=false). Bukti: `config/dompdf.php`.
- Spatie Permission (roles/permissions tables). Bukti: `config/permission.php`.

## 8) Testing & QA
- PHPUnit + Pest tersedia. `phpunit.xml` mengkonfigurasi suites Unit dan Feature, environment memori sqlite, queue/cache/session array drivers saat test.
- NPM QA scripts: stylelint, eslint, axe a11y via puppeteer, lighthouse (lhci), berbagai audit CSS. Bukti: `package.json` scripts `audit:*`.
- Composer scripts `test`: `@php artisan test` dan clearing config sebelum test. Pest listed in require-dev.

## 9) Skrip & Otomasi
- Composer scripts
  - `dev`: concurrently menyalakan `php artisan serve`, `php artisan queue:listen`, `php artisan pail`, dan `npm run dev`.
  - `test`: clear config lalu `artisan test`.
  - `post-create-project-cmd`: generate key, create sqlite db file, migrate.
  - `post-update-cmd`: publish laravel assets.
- NPM scripts
  - `build`, `dev` (Vite), berbagai `audit:*` (stylelint/eslint/a11y/lh/coverage/cascade/guard/contrast/zindex) dan `audit:all`, `audit:critical`.

## 10) Konfigurasi & Environment
- Variabel ENV yang dipakai (cuplikan kunci; tanpa nilai):
  - App: APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_KEY, APP_PREVIOUS_KEYS, APP_MAINTENANCE_DRIVER, APP_MAINTENANCE_STORE, APP_LOCALE, APP_FALLBACK_LOCALE, APP_FAKER_LOCALE.
  - Database: DB_CONNECTION, DB_URL, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_SOCKET, DB_CHARSET, DB_COLLATION, DB_FOREIGN_KEYS.
  - Redis: REDIS_CLIENT, REDIS_CLUSTER, REDIS_PREFIX, REDIS_PERSISTENT, REDIS_URL, REDIS_HOST, REDIS_USERNAME, REDIS_PASSWORD, REDIS_PORT, REDIS_DB, REDIS_CACHE_DB, REDIS_MAX_RETRIES, REDIS_BACKOFF_*.
  - Cache: CACHE_STORE, DB_CACHE_CONNECTION, DB_CACHE_TABLE, DB_CACHE_LOCK_CONNECTION, DB_CACHE_LOCK_TABLE, CACHE_PREFIX.
  - Session: SESSION_DRIVER, SESSION_LIFETIME, SESSION_EXPIRE_ON_CLOSE, SESSION_ENCRYPT, SESSION_CONNECTION, SESSION_TABLE, SESSION_STORE, SESSION_COOKIE, SESSION_PATH, SESSION_DOMAIN, SESSION_SECURE_COOKIE, SESSION_HTTP_ONLY, SESSION_SAME_SITE, SESSION_PARTITIONED_COOKIE.
  - Queue: QUEUE_CONNECTION, DB_QUEUE_CONNECTION, DB_QUEUE_TABLE, DB_QUEUE, DB_QUEUE_RETRY_AFTER, BEANSTALKD_*, SQS_*, AWS_* (region, keys), REDIS_QUEUE_*.
  - Mail: MAIL_MAILER, MAIL_SCHEME, MAIL_URL, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_EHLO_DOMAIN, MAIL_LOG_CHANNEL, MAIL_SENDMAIL_PATH.
  - Services: POSTMARK_TOKEN, RESEND_KEY, SLACK_BOT_USER_OAUTH_TOKEN, SLACK_BOT_USER_DEFAULT_CHANNEL, WHATSAPP_API_URL, WHATSAPP_API_KEY.
  - Filesystems: FILESYSTEM_DISK, AWS_BUCKET, AWS_URL, AWS_ENDPOINT, AWS_USE_PATH_STYLE_ENDPOINT.
  - Permission: default config menggunakan cache store 'default'.
- File penting
  - `.env.example` ada. Docker/k8s manifests tidak ditemukan dalam struktur yang dipindai.

## 11) Peta Ketergantungan
- Composer (prod)
  - laravel/framework (^12.0) – web framework
  - barryvdh/laravel-dompdf (^3.1) – PDF generation
  - spatie/laravel-permission (^6.21) – roles & permissions
  - simplesoftwareio/simple-qrcode (^4.2) – QR code
  - laravel/tinker (^2.10.1) – REPL
- Composer (dev)
  - laravel/breeze – auth scaffolding
  - laravel/pail – log viewer
  - laravel/pint – code style
  - laravel/sail – dev env
  - nunomaduro/collision – test output
  - phpunit/phpunit (11.5.33) – testing
  - pestphp/pest (3.8.4) – testing framework
  - fakerphp/faker, mockery/mockery – tests
- NPM (dev)
  - vite (^7), laravel-vite-plugin (^2) – build
  - tailwindcss (^3.4), @tailwindcss/forms – styling
  - alpinejs (^3.4.2) – interactivity
  - eslint (^9), typescript-eslint, eslint plugins (import, jsx-a11y, unicorn, vue) – lint
  - stylelint (^16) + configs & plugins – CSS lint
  - puppeteer, axe-core, lhci – a11y/perf audits
  - axios (^1.11.0) – HTTP client
  - concurrently, npm-run-all – orchestration

## 12) Area Risiko & TODO
- Debug routes tanpa auth penuh
  - `/debug/doc-probe` dan `/debug/file-keys` tidak diberi middleware auth (lihat `routes/web.php` bagian Debug Routes). Potensi kebocoran informasi/penyalahgunaan file upload. Disarankan lindungi dengan `auth` + role gate atau batasi ke APP_DEBUG.
- Endpoints API langsung di `routes/api.php` mengembalikan data sensitif permintaan/proses via request number atau ID tanpa middleware. Jika dipakai untuk generator lokal saja, sebaiknya dibatasi (signed routes/temporary token/IP allowlist).
- Queue/Cache/Session default menggunakan database; pastikan migrasi tabel terkait telah dijalankan dan indeks performa memadai (ada migrasi `add_performance_indexes.php`).
- DomPDF `enable_remote` = false (aman), namun chroot base_path; pastikan path asset PDF sesuai (gunakan storage disk publik jika perlu).
- Role management: Navigasi menggunakan `Auth::user()->role` string (lihat navbar), sementara paket Spatie Permission tersedia; hindari duplikasi konsep role manual vs permission package.
- Tidak terlihat mekanisme rate limiting pada endpoint publik `/track` (GET/POST) dan `/track/{tracking_number}.json`.
- N+1 Query potensi pada API generator `routes/api.php` – sudah menggunakan `with([...])`, relatif baik; cek akses koleksi terkait di views/controllers lain.
- Scheduler kosong: jika ada pekerjaan rutin (cleanup, re-issue numbers, dsb.), belum terdefinisi.

Catatan sumber:
- Debug routes referensi: `routes/web.php` lines sekitar 147-225 (termasuk `debug.file-keys`, `debug.ba`, `debug.process`).

## 13) Lampiran
- Pohon direktori tingkat 3 (ringkas; exclude vendor, node_modules, storage, .git)

```
app/
  Console/
    Commands/
  Enums/
  Events/
  Http/
    Controllers/
    Middleware/
    Requests/
  Listeners/
  Models/
  Policies/
  Providers/
  Services/
  Support/
  View/
bootstrap/
  app.php
  providers.php
config/
  app.php
  auth.php
  cache.php
  database.php
  dompdf.php
  filesystems.php
  logging.php
  mail.php
  permission.php
  queue.php
  services.php
  session.php
database/
  factories/
  migrations/
    2025_09_29_03_create_test_requests_table.php
    2025_09_29_044652_create_samples_table.php
    2025_10_02_152107_create_sample_test_processes_table.php
    ...
routes/
  web.php
  api.php
  auth.php
  console.php
resources/
  css/
    app.css
    fonts.css
    icons.css
    theme-dark.css
    theme-semantic.css
    ui-scope.css
  js/
    app.js
    bootstrap.js
    utils/
      list-fetcher.js
  views/
    layouts/
      app.blade.php
      guest.blade.php
      navigation.blade.php
    components/
      ...
    dashboard.blade.php
    landing.blade.php
    statistics/
    tracking/
    requests/
    samples/
    sample-processes/
    deliveries/
    settings/
    database/
    pdf/
public/
  storage/
patcher/
patches/
output/
  BA_Penyerahan_Ringkasan_*.html
scripts/
  ...
```

- Daftar file konfigurasi utama dengan path
  - `config/app.php`, `config/auth.php`, `config/cache.php`, `config/database.php`, `config/dompdf.php`, `config/filesystems.php`, `config/logging.php`, `config/mail.php`, `config/permission.php`, `config/queue.php`, `config/services.php`, `config/session.php`
  - `phpunit.xml`
  - `composer.json`, `composer.lock`
  - `package.json`, `package-lock.json`
  - `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `.eslintrc.cjs`, `.stylelintrc.cjs`, `lighthouserc.json`
  - `.env.example`

---

Generated at: 11 October 2025, 00:00 local time
