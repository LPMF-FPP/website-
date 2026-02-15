# Current Task: QMH v2 (Form-First HTML, DOCX Deprecation)

## Objective

Refactor QMH ke baseline final v2:

1. Alur utama tanpa DOCX/OnlyOffice.
2. Preview dokumen manual saat klik `Simpan Draft`.
3. PDF dari HTML/template sistem + watermark + audit tetap utuh.

## Progress

- [x] **Spec Refactor (v2 baseline)**
    - [x] Refactor `tech-spec-wip.md` jadi final v2 (tanpa DOCX sebagai jalur utama).
    - [x] Wireframe teks final untuk create, preview gate, detail, reports.

- [x] **Sprint A - UX Gate (Preview before save)**
    - [x] Modal preview ditrigger saat submit draft.
    - [x] User dapat `Kembali Edit` atau `Lanjut Simpan Draft`.
    - [x] Build frontend lulus setelah perubahan.

- [x] **Sprint B - Soft Deprecation DOCX**
    - [x] Sembunyikan aksi `Edit DOCX` dari UI detail dokumen.
    - [x] Putus ketergantungan edit flow terhadap `office-session`.
    - [x] Nonaktifkan route DOCX/Office dari jalur utama.
    - [x] Sesuaikan test suite dari jalur DOCX/Office ke baseline HTML-only (legacy tests di-skip saat jalur dimatikan).

- [x] **Sprint C - Hard Cleanup DOCX**
    - [x] Hapus endpoint/controller/service DOCX legacy (route + action editDocx sudah dihapus, service legacy juga sudah dihapus).
    - [ ] Hapus config `quality.office.*` dan `quality.export.docx_to_pdf` yang tidak dipakai.
    - [ ] Migration cleanup kolom legacy (`source_docx_*`, `export_pdf_from_docx`) setelah stabil.

## Verification Checklist

- [ ] `php vendor/bin/pest`
- [ ] `npm run audit:critical`
- [ ] `./vendor/bin/pint`
- [ ] UAT flow: create -> preview gate -> save draft -> submit/review/approve -> download.

## Rollout Notes

1. Deploy Sprint B dahulu (soft deprecate) untuk menurunkan risiko.
2. Monitoring error route/API 24-48 jam pasca deploy.
3. Lanjut Sprint C hanya jika tidak ada regresi operasional.

---

# Next Task: QMH Form Builder (Formulir/FR)

## Objective

Bangun Form Builder UI untuk schema formulir (FR) dan perluas tipe field v1, dengan validasi server-side dan render konsisten (create/edit, preview, PDF).

## Scope (v1)

- Builder UI di halaman edit template QMH (replace JSON textarea sebagai primary UI, JSON tetap jadi output).
- Tipe field v1: `section`, `text`, `textarea`, `list`, `select`, `checkbox`, `date`, `number`.
- Validasi server-side: required + type check saat simpan jawaban.
- Rendering: input, structured preview, dan PDF.

## Tasks

- [ ] Spec final + acceptance criteria terkunci di `WALKTHROUGH.md`
- [ ] Implement schema validator (PHP support/service)
- [ ] Implement Form Builder UI (Alpine) di template editor
- [ ] Render field types v1 di halaman create/edit QMH
- [ ] Update structured preview & PDF untuk type baru (checkbox/select/date/number/section)
- [ ] Add/Update Pest tests (schema + save answers + PDF HTML)

## Verification Checklist

- [ ] `php vendor/bin/pest`
- [ ] `npm run audit:critical`
- [ ] `./vendor/bin/pint`
- [ ] UAT: edit template FR -> buat dokumen -> preview -> PDF
