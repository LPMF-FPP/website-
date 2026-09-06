# AGENTS.md - LPMF LIMS Agent Guide

Panduan operasional untuk coding agent di repository ini. Utamakan integritas domain laboratorium, keamanan akses, dan perubahan kecil yang dapat diverifikasi daripada kecepatan atau refactor luas.

## Project Snapshot

- Aplikasi: LPMF LIMS, sistem manajemen laboratorium farmasi forensik dalam konteks pemerintahan
- Backend: PHP `^8.2`, Laravel `^12.0`, layered monolith
- UI utama: Blade server-rendered, Alpine.js 3, Tailwind CSS 3, Vite 7
- Library UI tambahan: TipTap 3; React 18 tersedia untuk area tertentu, tetapi bukan stack UI default
- Database utama: PostgreSQL
- Authorization: middleware/policy dan `spatie/laravel-permission`
- Testing: Pest/PHPUnit 11, Laravel Dusk, Node test runner
- Quality tooling: Pint, ESLint 10, Stylelint 16, axe-core, Puppeteer, audit scripts kustom
- Bahasa: copy UI dalam Bahasa Indonesia; identifier code dalam English
- Runtime frontend/audit: Node.js 20+

## Instruction Priority

1. Instruksi system, developer, dan user pada sesi aktif
2. `AGENTS.md` ini
3. Dokumen relevan di `docs/`, terutama `docs/project-context.md`
4. `.github/copilot-instructions.md`
5. Konvensi lokal pada code yang sedang disentuh

Jika panduan berbeda, ikuti sumber dengan prioritas lebih tinggi. Jangan menganggap dokumentasi lama lebih benar daripada package/config/code aktual.

## Required Workflow

1. Klasifikasikan task: Laravel/PHP, Blade/Alpine UI, React area, testing, review, planning, research, atau konfigurasi agent.
2. Muat skill yang relevan sebelum implementasi. Gunakan `npx skills list` untuk project skill, `npx skills list --global` untuk global skill, lalu pastikan skill tersebut tersedia melalui `skill` tool pada sesi aktif.
3. Mulai discovery dengan `morph-mcp_codebase_search`, lalu persempit menggunakan `glob`, `grep`, dan `read`.
4. Baca config, route, service, policy, model, test, dan docs yang berhubungan sebelum memutuskan solusi.
5. Implementasikan diff terkecil yang benar dan pertahankan pola area yang disentuh.
6. Jalankan verifikasi terkecil yang relevan, perbaiki failure, lalu ulangi sampai lolos.
7. Laporkan path yang berubah, command verifikasi, hasilnya, dan risiko yang masih tersisa.

Jangan berhenti pada rencana jika user meminta perubahan code. Kerjakan end-to-end selama feasible.

## Repository Search and Editing

- Gunakan semantic search terlebih dahulu untuk task yang memerlukan pemahaman codebase.
- Gunakan `glob` untuk pencarian file dan `grep` untuk pencarian isi; jangan memakai shell `find`/`grep` untuk pencarian normal.
- Gunakan `read` untuk membuka file dan `apply_patch` untuk semua edit manual.
- Jangan membuat patcher/script sementara untuk menulis ulang source atau CSS.
- Jangan menyentuh perubahan worktree yang tidak terkait dan jangan merapikan file lain tanpa kebutuhan task.
- Jangan memakai `git reset --hard`, `git checkout --`, atau command destruktif lain untuk membersihkan worktree.
- Commit, amend, push, atau PR hanya jika diminta eksplisit.

## Architecture Rules

- Perlakukan aplikasi sebagai Laravel 12 layered monolith.
- Controller berfungsi untuk orkestrasi; business logic non-trivial tetap di service/action/domain layer yang sudah ada.
- Gunakan Form Request untuk validation/authorization yang reusable atau kompleks.
- Gunakan policy, permission middleware, dan service boundary; jangan menggantinya dengan role check ad-hoc.
- Pertahankan middleware di `bootstrap/app.php`, termasuk session-backed API behavior dan pengecualian CSRF yang sudah disengaja.
- Gunakan named route dan `route()` untuk URL internal bila pola area mendukungnya.
- Gunakan Eloquent relationship dan query pattern yang sudah ada; jangan interpolasi input ke raw SQL.
- Jangan menambah global helper baru kecuali kebutuhan lintas area benar-benar konkret.
- Jangan menambah dependency atau mengubah arsitektur frontend tanpa kebutuhan yang jelas.

## Domain Integrity

- Pertahankan lifecycle utama: `TestRequest -> Sample -> SampleTestProcess -> TestResult`.
- Anggap workflow transition, numbering, inventory movement, sample disposal, delivery, LHU/result lifecycle, dan document generation sebagai area high-risk.
- Jangan membuat shortcut state, mutasi langsung, atau jalur controller baru yang melewati service/policy/observer yang ada.
- Perubahan high-risk harus menguji success path, invalid transition, authorization failure, dan rollback/atomicity bila relevan.
- Endpoint export, download, PDF, label, dan dokumen bersifat sensitif; pertahankan permission, signed route, throttle, audit log, dan scope akses.
- Alur WhatsApp/GOWA harus idempotent, auditable, retry-safe, dan tidak menghasilkan pengiriman/mutasi duplikat.
- Integrasi Google Drive, S3, Sentry, WhatsApp, dan provider eksternal harus menggunakan fake/mock/sandbox pada test dan tidak melakukan live side effect tanpa otorisasi.
- Jangan menjalankan migration destructive, database reset, rollback, atau `migrate:fresh` tanpa persetujuan user.

## Security and Secrets

- Fail closed pada authorization, validation, signature, dan workflow guard.
- Jangan melepas middleware `auth`, `verified`, `permission`, `any_permission`, `throttle`, atau audit protection untuk mempermudah implementasi.
- Jangan membaca atau menampilkan nilai mentah dari `.env*`, `.deploy.local`, token, cookie, credential, private key, atau payload sensitif.
- Redact credential-like output sebagai `***REDACTED***` dan gunakan placeholder seperti `DB_PASSWORD=<provided-by-user>`.
- Jangan mencatat credential, token, raw request body sensitif, atau data personal yang tidak diperlukan ke log.
- Perlakukan executable tools, database write, external form submission, queue restart, dan provider call sebagai side effect; lakukan hanya dalam scope yang diminta.

## PHP and Laravel Style

- Ikuti PSR-12 dan formatter Pint.
- Gunakan `declare(strict_types=1);` pada file PHP baru bila konsisten dengan area sekitarnya.
- Gunakan parameter type dan return type eksplisit untuk method non-trivial.
- Naming: class `PascalCase`, method/property `camelCase`, database field `snake_case`.
- Gunakan constructor injection dan `private readonly` dependency bila sesuai pola lokal.
- Model harus eksplisit untuk `$fillable`, `$casts`, dan typed relationships bila area tersebut memakai pola ini.
- Jangan menaruh business logic di Blade view, route closure, accessor, atau observer tanpa alasan domain yang jelas.
- Saat mengubah kolom migration dengan `change()`, nyatakan ulang modifier yang harus dipertahankan.

## Frontend Rules

- Default perubahan UI adalah Blade + Alpine, bukan React atau SPA baru.
- React hanya digunakan jika file/area yang disentuh memang React; repository ini bukan Next.js, jadi jangan memuat skill atau MCP Next.js untuk pekerjaan normal.
- Reuse Blade component, Alpine store/data, Tailwind token, dan pattern UI yang sudah ada.
- Copy UI harus Bahasa Indonesia dan pesan error harus spesifik serta dapat ditindaklanjuti.
- Semua control harus semantic, keyboard-operable, memiliki visible focus state, dan touch target minimal 44px pada mobile.
- Jangan menghapus focus outline tanpa replacement yang terlihat.
- Sediakan empty, loading, validation, success, dan error state sesuai jenis UI.
- Pertahankan state navigasi di URL untuk filter, tab, search, sort, dan pagination bila relevan.
- Hormati `prefers-reduced-motion`; animasikan hanya `transform` dan `opacity`.
- Uji layout untuk mobile dan desktop, long content, empty/dense state, serta overflow.
- Gunakan Axios melalui `resources/js/bootstrap.js` untuk default CSRF yang aman.
- Gunakan `Alpine.data()`/`Alpine.store()` sesuai pola registrasi aplikasi.

## CSS and JavaScript Constraints

- Source of truth lint: `eslint.config.cjs` dan `.stylelintrc.cjs`.
- JavaScript: gunakan `const`, lalu `let` bila berubah; hindari `var`, `eval`, dan `new Function`.
- Gunakan strict equality dan import yang grouped, stable, serta bebas duplikasi.
- CSS specificity maksimal `0,4,0`, tanpa ID selector, maksimal 4 compound selector.
- Hindari `!important` kecuali edge case terkontrol yang sesuai konvensi lokal.
- Ikuti property ordering dan custom-property prefix yang diizinkan config Stylelint.
- Safe Mode: `styles/pd-*.css` tidak boleh mengandung layout properties seperti `margin`, `padding`, `width`, `height`, `position`, `display`, `flex`, `grid`, `gap`, `overflow`, atau `transform`.
- Jangan melakukan style churn atau reformat luas di luar scope task.

## Skill Routing

Sumber kebenaran project skill adalah output `npx skills list`. Pada audit terakhir, semua project skill berada di `.agents/skills/` dan tersedia untuk OpenCode, Codex, Gemini CLI, serta GitHub Copilot. Jangan menganggap skill global atau isi `.opencode/skills/` sebagai project skill bila tidak muncul pada command tersebut.

- Routing dan bantuan BMAD: `bmad-help`.
- Implementasi umum: `bmad-build`; unattended iteration hanya ketika diminta: `bmad-build-auto`.
- Implementasi story: `bmad-dev-story`; `bmad-quick-dev` dan `bmad-dev-auto` hanya compatibility shim.
- Specification dan product planning: `bmad-product-brief`, `bmad-prd`, `bmad-prfaq`, `bmad-spec`, `bmad-architecture`, `bmad-create-epics-and-stories`, dan `bmad-create-story`.
- Skill planning lama yang masih terpasang: `bmad-create-prd`, `bmad-edit-prd`, `bmad-validate-prd`, dan `bmad-create-architecture`; gunakan hanya bila dipanggil eksplisit atau diarahkan oleh skill utama.
- UX planning: `bmad-ux`.
- Sprint execution: `bmad-sprint-planning`, `bmad-sprint-status`, `bmad-correct-course`, dan `bmad-retrospective`.
- Review utama: `bmad-review` atau `bmad-code-review` sesuai artifact; lensa khusus tersedia melalui `bmad-review-adversarial-general`, `bmad-review-edge-case-hunter`, dan `bmad-review-verification-gap`.
- Editorial review: `bmad-editorial-review`, `bmad-editorial-review-prose`, dan `bmad-editorial-review-structure`.
- Testing dan quality architecture: `bmad-tea`, `bmad-testarch-atdd`, `bmad-testarch-automate`, `bmad-testarch-ci`, `bmad-testarch-framework`, `bmad-testarch-nfr`, `bmad-testarch-test-design`, `bmad-testarch-test-review`, dan `bmad-testarch-trace`.
- Test learning dan E2E existing behavior: `bmad-teach-me-testing` dan `bmad-qa-generate-e2e-tests`.
- Research utama: `bmad-deep-recon`; compatibility skill yang masih terpasang adalah `bmad-market-research`, `bmad-domain-research`, dan `bmad-technical-research`.
- Ideation dan facilitation: `bmad-brainstorming`, `bmad-forge-idea`, `bmad-advanced-elicitation`, `bmad-party-mode`, `bmad-cis-design-thinking`, `bmad-cis-innovation-strategy`, `bmad-cis-problem-solving`, dan `bmad-cis-storytelling`.
- Agent persona: `bmad-agent-analyst`, `bmad-agent-pm`, `bmad-agent-ux-designer`, `bmad-agent-architect`, dan `bmad-agent-dev`.
- CIS persona: `bmad-cis-agent-brainstorming-coach`, `bmad-cis-agent-creative-problem-solver`, `bmad-cis-agent-design-thinking-coach`, `bmad-cis-agent-innovation-strategist`, `bmad-cis-agent-presentation-master`, dan `bmad-cis-agent-storyteller`.
- Agent, workflow, dan module authoring: `bmad-agent-builder`, `bmad-workflow-builder`, `bmad-module-builder`, `bmad-customize`, dan `bmad-bmb-setup`.
- Agent instruction maintenance: `bmad-project-context`; `bmad-generate-project-context` dan `bmad-document-project` hanya compatibility shim.
- Evaluation dan human checkpoint: `bmad-eval-runner` dan `bmad-checkpoint-preview`.

Jangan memuat banyak skill secara defensif. Pilih set minimum yang benar-benar memiliki ownership atas task.

## Global Skill Routing

Sumber kebenaran global skill adalah output `npx skills list --global`. Global skill melengkapi project skill, bukan menggantikannya: muat project skill pemilik workflow terlebih dahulu, lalu tambahkan global specialist hanya jika scope memerlukannya.

- Laravel 11/12: `laravel-11-12-app-guidelines` untuk perubahan yang secara material dimiliki framework Laravel.
- Frontend implementation: `frontend-design` sebagai baseline untuk UI baru atau perubahan visual; untuk redesign existing UI gunakan `redesign-existing-projects`.
- Anti-slop UI: muat `antislop` lalu concern minimum yang relevan, yaitu `antislop-ui`, `antislop-human`, `antislop-layoutmobile`, `antislop-copywriting`, atau `antislop-code`. Ikuti pertanyaan mode DURING/AFTER dari skill dan jangan memuat semua turunannya tanpa kebutuhan.
- UI/UX audit dan perbaikan: `ui-ux-pro-max` untuk design intelligence dan `web-design-guidelines` untuk review accessibility/interface guidelines.
- Visual direction premium: pilih satu direction owner sesuai brief, misalnya `design-taste-frontend`, `high-end-visual-design`, `minimalist-ui`, `industrial-brutalist-ui`, atau `gpt-taste`; jangan menumpuk beberapa style skill yang saling bersaing.
- Compatibility visual: `design-taste-frontend-v1` hanya jika exact v1 behavior dibutuhkan.
- Design system Tailwind: `tailwind-design-system` hanya untuk component/token standardization dan setelah memastikan guidance kompatibel dengan Tailwind CSS 3 proyek ini.
- React performance: `vercel-react-best-practices` hanya pada area React yang nyata; jangan menerapkannya pada Blade/Alpine.
- Copy marketing: `copywriting`; tambahkan `antislop-copywriting` untuk prose hygiene bila relevan.
- Brand dan visual assets: `brandkit` untuk brand system; `imagegen-frontend-web` atau `imagegen-frontend-mobile` untuk design reference berbasis image.
- Image-led implementation: `image-to-code` hanya ketika task memang meminta workflow generate-reference-then-implement.
- Stitch handoff: `stitch-design-taste` hanya untuk membuat atau memperbarui `DESIGN.md` bagi Google Stitch.
- Output lengkap tanpa placeholder: `full-output-enforcement` hanya untuk deliverable yang memang harus exhaustive dan unabridged.
- Skill discovery: `find-skills` ketika user mencari capability baru atau skill yang belum terpasang.

Global skill yang terverifikasi berasal dari `vercel-labs/skills`, `anthropics/skills`, `vercel-labs/agent-skills`, `coreyhaines31/marketingskills`, `nextlevelbuilder/ui-ux-pro-max-skill`, `wshobson/agents`, `thienanblog/awesome-ai-agent-skills`, `Leonxlnx/taste-skill`, dan `miqdadbadjuber/anti-slop`. Jangan menebak skill lain dari nama source; gunakan hanya nama yang muncul pada inventaris global.

## MCP and External Tool Routing

Gunakan tool yang tersedia pada sesi, bukan asumsi berdasarkan package npm atau file konfigurasi lama.

- Morph MCP: `morph-mcp_codebase_search` untuk semantic discovery lokal; setelah itu gunakan `glob`/`grep`/`read` untuk bukti presisi.
- Morph GitHub search: gunakan `morph-mcp_github_codebase_search` untuk membaca implementation upstream tanpa clone, terutama saat dependency error atau behavior library perlu ditelusuri.
- Context7: wajib untuk pertanyaan/API library dan framework yang version-sensitive. Jalankan `context7_resolve-library-id`, lalu `context7_query-docs`; maksimal 3 panggilan Context7 per pertanyaan.
- Firecrawl: gunakan `firecrawl_search` untuk menemukan sumber web, `firecrawl_scrape` untuk satu URL, `firecrawl_map` untuk inventaris URL, `firecrawl_crawl` untuk beberapa halaman, dan agent research hanya untuk sintesis multi-source yang memang perlu.
- Chrome DevTools: gunakan untuk browser verification, console/network inspection, accessibility snapshot, screenshot, Lighthouse, dan performance trace pada aplikasi yang sedang berjalan.
- `next-devtools_*`: hanya untuk repository Next.js yang benar-benar menjalankan Next.js 16+; tidak berlaku pada aplikasi Laravel ini.
- `@playwright/mcp` di `package.json` adalah dependency, bukan bukti bahwa Playwright MCP terhubung pada sesi. Gunakan hanya bila tool-nya benar-benar diekspos.
- MCP resources/templates yang kosong bukan error; gunakan function tools yang tersedia langsung.

Untuk library docs, pilih Context7 daripada web search. Untuk evidence web non-library, pilih Firecrawl. Untuk code lokal, jangan mengirim source proprietary ke query eksternal.

## Commands

```bash
# Install dependencies tanpa lifecycle scripts yang tidak perlu
composer install --no-plugins --no-scripts
npm ci --ignore-scripts

# Local development
composer run dev
php artisan serve
npm run dev

# Production frontend build
npm run build
```

```bash
# Focused PHP tests
php vendor/bin/pest tests/Feature/Path/ExampleTest.php
php vendor/bin/pest --filter "test name"

# Full/targeted suites
composer test
npm run test:php
npm run test:search
npm run test:e2e

# Format and lint
./vendor/bin/pint --dirty
npm run audit:eslint
npm run audit:stylelint

# Frontend quality gates
npm run audit:guard
npm run audit:critical
npm run audit:a11y
npm run audit:all
```

Gunakan focused test terlebih dahulu. Full Pest/Dusk/audit suite dapat mahal dan memerlukan service/browser; jalankan jika scope menuntut atau user meminta.

## Verification Matrix

- PHP logic kecil: focused Pest test + `./vendor/bin/pint --dirty`.
- Controller/route/auth: focused Feature test, termasuk forbidden/invalid case.
- Workflow, inventory, numbering, result, delivery: focused domain/Feature tests dan rollback/transition coverage.
- Blade/Alpine: relevant test + ESLint bila JS berubah + browser check untuk behavior interaktif.
- CSS/Tailwind: Stylelint + `audit:guard`/`audit:critical` sesuai file + browser responsive check.
- Build config/dependency/frontend entry: `npm run build`.
- Dusk/browser flow: target Dusk test; pastikan environment browser tersedia.
- Docs-only/agent-guidance change: periksa referensi path/command/skill, Markdown structure, dan `git diff`; tidak perlu menjalankan application tests.

Jangan menyatakan verifikasi lulus bila command tidak dijalankan. Jika environment menghalangi, sebutkan blocker secara eksplisit.

## Documentation

- Jangan membuat file Markdown dokumentasi ad-hoc.
- Gunakan `WALKTHROUGH.md` untuk implementation notes bila dokumentasi perubahan memang diminta.
- File standalone yang sudah diizinkan tetap boleh dipelihara, termasuk `README.md`, checklist repo, `report/README.md`, `.github/copilot-instructions.md`, `AGENTS.md`, dan artifact BMAD di `docs/`.
- `AGENTS.md` adalah kontrak operasional utama; `docs/project-context.md` adalah ringkasan lean. Selaraskan keduanya saat stack atau guardrail berubah material.
- Jangan memperbarui docs untuk setiap perubahan kecil bila user tidak meminta dan behavior sudah jelas dari code/test.

## Completion Standard

Sebelum selesai, pastikan:

- Scope user terpenuhi tanpa perubahan samping yang tidak diminta.
- Domain guard, authorization, middleware, dan auditability tetap utuh.
- Tidak ada secret atau live external side effect yang bocor.
- Verification terkecil yang relevan sudah dijalankan dan lolos, atau blocker dilaporkan.
- Final response menyebut file berubah, verifikasi, dan residual risk secara ringkas.
