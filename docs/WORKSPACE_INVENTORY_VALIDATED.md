# WORKSPACE INVENTORY (Validated)

Validated at: 11 October 2025

## 1) Ringkasan Proyek ✅
- Tujuan aplikasi & peran utama: Sistem internal pengelolaan permintaan pengujian laboratorium (Lidik/Sidik), manajemen sampel, proses pengujian bertahap, pembuatan dokumen (Berita Acara, laporan), penyerahan hasil, tracking, statistik, dan pengaturan.
  - Sumber: `routes/web.php` (Dashboard, Requests, Samples, Sample Processes, Delivery, Tracking, Statistics, Settings); `app/Models/*` (TestRequest, Sample, SampleTestProcess, Document).
- Arsitektur: Monolith Laravel 12 (SSR Blade) + Vite + Alpine.js. Tidak ada SPA/router FE.
  - Sumber: `composer.json` (laravel/framework ^12.0), `vite.config.js`, `resources/js/app.js` (Alpine).
- Bahasa & runtime: PHP ^8.2; Node tooling untuk build (Vite 7, Tailwind 3).
  - Sumber: `composer.json` ("php": "^8.2"), `package.json` (vite ^7.0.4, tailwindcss ^3.4.18).

## 2) Backend ✅
- Framework & versi: Laravel ^12.0; paket mayor: dompdf, spatie/permission, simple-qrcode.
  - Sumber: `composer.json` require.
- Struktur folder backend: `app/` (Http, Models, Policies, Providers, Services, Support, View, Console), `config/`, `database/`, `routes/`.
  - Sumber: struktur repo.
- Service Provider / Middleware / Policy / Gate / Job / Event / Listener / Scheduler:
  - Provider: `app/Providers/AppServiceProvider.php`.
  - Middleware: `app/Http/Middleware/ApplyLocaleFromSettings.php`.
  - Policy: `app/Policies/InvestigatorDocumentPolicy.php`.
  - Gates: penggunaan `can:manage-settings` pada group settings (route-level gate).
  - Queue Jobs: tidak terlihat kelas Job khusus; queue dikonfigurasi (lihat Konfigurasi).
  - Events/Listeners: folder tersedia; detail tidak kritikal untuk validasi ini.
  - Scheduler: `routes/console.php` hanya command `inspire`; `app/Console/Kernel.php` tidak ditemukan (scheduler kustom tidak ada).
- Model & Relasi penting:
  - TestRequest: `belongsTo(Investigator)`, `belongsTo(User)`, `hasMany(Sample)`, `hasMany(Document)`; auto-generate `request_number`.
    - Sumber: `app/Models/TestRequest.php` (boot creating, generateRequestNumber()).
  - Sample: `belongsTo(TestRequest)`, `hasOne(TestResult)`, `hasMany(SampleTestProcess)`, `belongsTo(User,'assigned_analyst_id')`; auto-generate `sample_code`.
    - Sumber: `app/Models/Sample.php`.
  - SampleTestProcess: `belongsTo(Sample)`, `belongsTo(User,'performed_by')`; enum cast `stage`, JSON `metadata`.
    - Sumber: `app/Models/SampleTestProcess.php`.
- Validasi & Form Request signifikan: `ProfileUpdateRequest` (validasi name, email unique untuk user saat ini).
  - Sumber: `app/Http/Requests/ProfileUpdateRequest.php`.
- Konfigurasi queue/cache/session/storage: default queue `database`, cache `database`, session `database`, storage disks kustom (documents, test_results, samples, official_docs, generated_docs, lab_images, archives) + s3.
  - Sumber: `config/queue.php`, `config/cache.php`, `config/session.php`, `config/filesystems.php`.

## 3) Routes Backend ⚠️ (ditambahkan endpoint health)
- Perbaikan: Menambahkan endpoint kesehatan read-only tanpa auth (lihat bagian B) dan me-review debug routes tanpa auth.
- Tabel hasil parsing `routes/*.php` (gabungan web/api/auth/console):

| Method | Path | Name | Controller@action | Middleware |
|---|---|---|---|---|
| GET | /health | health | HealthController@index | - |
| POST | /locale/{locale} | locale.switch | LocaleController@switch | - |
| GET | / | - | landing or redirect(dashboard) | - |
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
| GET | /samples | samples.index | redirect->samples.test.create | auth, verified |
| resource | /sample-processes | sample-processes.* | SampleTestProcessController | auth, verified |
| GET | /sample-processes/{id}/form/{stage} | sample-processes.generate-form | SampleTestProcessController@generateForm | auth, verified |
| GET | /sample-processes/{id}/lab-report | sample-processes.lab-report | SampleTestProcessController@generateReport | auth, verified |
| POST | /samples/{sample}/ready-for-delivery | samples.ready-for-delivery | SampleTestProcessController@markAsReadyForDelivery | auth, verified |
| resource (except show) | /analysts | analysts.* | AnalystController | auth, verified |
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
| GET | /design-examples | design.examples | view('design-examples') | auth, verified |
| GET | /debug/doc-probe | debug.doc-probe | DebugDocController@probe | - |
| GET | /debug/file-upload | debug.file-upload | closure -> file(public/debug-file-upload.html) | - |
| MATCH GET,POST | /debug/file-keys | debug.file-keys | closure -> JSON keys | - |
| GET | /debug/ba/{id} | debug.ba | closure -> RequestController@generateBeritaAcara | auth |
| GET | /debug/process/{id} | debug.process | closure uses DocumentService | auth |
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
| GET | /api/requests/{requestNumber} | - | closure -> BA JSON | - |
| GET | /api/sample-processes/{processId} | - | closure -> LHU JSON | - |

Sumber: `routes/web.php`, `routes/api.php`, `routes/auth.php`, `routes/console.php`.

## 4) Database & Migrasi ✅
- Tabel utama & kunci: users, investigators, test_requests, samples, documents, test_results, survey_responses, sessions, password_reset_tokens, failed_jobs, cache, sample_test_processes, system_settings, deliveries, delivery_items.
- Kunci: FK test_requests.investigator_id/user_id; samples.test_request_id; documents.test_request_id/generated_by; sample_test_processes.sample_id/performed_by. Timestamps umum. Enums workflow.
- Sumber: daftar file di `database/migrations/*` dan potongan yang dibaca.

## 5) Frontend ✅
- Blade + Alpine.js; entry: `resources/js/app.js`; Vite config input CSS/JS; Tailwind + PostCSS.
- Pemetaan halaman via `resources/views/...` dan navbar `resources/views/layouts/navigation.blade.php`.
- Tidak ada router SPA.

## 6) Resources & Aset ✅
- Layouts (`app.blade.php`, `guest.blade.php`, `navigation.blade.php`), komponen UI (button, nav-link, modal, dsb.).
- Tailwind utility layer di `resources/css/app.css`; tokens semantik (`theme-semantic.css`, `theme-dark.css`).

## 7) Integrasi & Layanan Eksternal ✅
- Email (smtp/ses/postmark/resend/log), Slack notifications, WhatsApp API, AWS S3, AWS SES, DomPDF; Spatie Permission.
- Sumber: `config/services.php`, `config/filesystems.php`, `config/mail.php`, `config/dompdf.php`, `config/permission.php`.

## 8) Testing & QA ✅
- PHPUnit + Pest; phpunit.xml mengatur sqlite in-memory dan drivers array saat test.
- NPM audits (stylelint/eslint/axe/lhci) tersedia.

## 9) Skrip & Otomasi ✅
- Composer `dev` (concurrently serve/queue/pail/vite), `test` (artisan test). Post-create-project migrasi otomatis.
- NPM `build`, `dev`, `audit:*` berbagai pemeriksaan.

## 10) Konfigurasi & Environment ✅
- ENV keys terdaftar dari config: app, database, redis, cache, session, queue, mail, services, filesystems.
- `.env.example` tersedia.

## 11) Peta Ketergantungan ✅
- Composer prod: laravel/framework, barryvdh/laravel-dompdf, spatie/laravel-permission, simplesoftwareio/simple-qrcode, laravel/tinker.
- Composer dev: laravel/breeze, laravel/pail, laravel/pint, laravel/sail, nunomaduro/collision, phpunit, pest, fakerphp/faker, mockery.
- NPM dev: vite, laravel-vite-plugin, tailwindcss, @tailwindcss/forms, alpinejs, eslint (+plugins), stylelint (+configs), puppeteer, axe-core, lhci, axios, concurrently, npm-run-all.
- Sumber: `composer.json`, `package.json`.

## 12) Area Risiko & TODO ⚠️
Top 10 prioritas:
1) Debug routes tanpa auth penuh: `/debug/doc-probe`, `/debug/file-keys` (public) dapat bocor info. Sumber: `routes/web.php` bagian Debug.
2) API generator tanpa auth: `/api/requests/{requestNumber}`, `/api/sample-processes/{processId}` (public). Risiko data sensitif. Sumber: `routes/api.php`.
3) Role ganda: Navigasi memakai `Auth::user()->role` string, namun paket Spatie Permission tersedia. Risiko inkonsistensi/pengecekan otorisasi. Sumber: `resources/views/layouts/navigation.blade.php`.
4) Rate limiting tracking publik: `/track` GET/POST tanpa rate limit/anti-abuse (captcha). Sumber: `routes/web.php`.
5) Database queue/cache/session: pastikan migrasi tabel dan indeks. Jika belum, antrian gagal menumpuk. Sumber: `config/queue.php`, `config/cache.php`, `config/session.php`.
6) Document download signed routes: Pastikan masa berlaku/authorization cukup; non-signed debug endpoints bisa memintas. Sumber: `routes/web.php` database.docs.* vs debug.*.
7) Scheduler kosong: pekerjaan berkala (cleanup dokumen/expired signed URLs) belum terdefinisi. Sumber: `routes/console.php`.
8) Locale switch POST publik: pastikan CSRF aktif via web middleware. Pertimbangkan throttle. Sumber: `routes/web.php` `Route::post('/locale/{locale}', ...)`.
9) DomPDF resource constraints: `enable_remote` false (aman), tetapi chroot base_path—pastikan path asset PDF sesuai untuk semua template. Sumber: `config/dompdf.php`.
10) Health endpoint harus read-only dan non-blocking: ditambahkan (lihat B). Pastikan tidak menambah latensi.

## 13) Lampiran ✅
- Pohon direktori tingkat 3 (tanpa vendor/node_modules/storage/.git) telah dirangkum pada inventaris sebelumnya; struktur valid berdasarkan listing root dan subfolder.
- File konfigurasi utama tercantum: `config/*.php`, `phpunit.xml`, `composer.json`, `package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `.eslintrc.cjs`, `.stylelintrc.cjs`, `lighthouserc.json`, `.env.example`.

---

Catatan kutipan sumber:
- Routes web/api/auth/console: lihat masing-masing file pada repo (`routes/web.php`, `routes/api.php`, `routes/auth.php`, `routes/console.php`).
- Model & migrasi: `app/Models/*`, `database/migrations/*`.
- Konfigurasi: `config/*.php`.
