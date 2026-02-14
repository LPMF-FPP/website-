# Current Task: Fix Numbering Scan & Test Suite Hangs

## Goals

1.  **Fix Numbering Scan False Positives**: `lhu` and `ba_penyerahan` scopes detected false gaps due to inherited sequences.
2.  **Fix Test Suite Hanging**: `npm run test:php` hung indefinitely due to parallel PDF generation consuming all resources.

## Progress

- [x] **Numbering Fix**
    - [x] Add `INHERITED_SEQUENCE_SCOPES` constant in `NumberingRepairService`.
    - [x] Skip gap detection for inherited scopes.
    - [x] Update `scanProblems` API to return `uses_inherited_sequence` flag.
    - [x] Update UI (`numbering-repair.blade.php`) to show info banner for inherited scopes.
    - [x] Add tests (`NumberingRepairInheritedSequenceTest.php`).
    - [x] Deploy to production.

- [x] **Test Suite Fix**
    - [x] Identify root cause: Real PDF generation (`DomPDF`) in tests causing resource exhaustion.
    - [x] Mock `PdfRenderService` in `LhuNumberingGenerationTest.php`, `LhuGenerationTest.php`, `SampleDisposalTest.php`, `BeritaAcaraPenerimaanTest.php`.
    - [x] Mock `DomPDF` facade in `FormPreparationArchiveTest.php`, `QmhRevisionApprovalDownloadTest.php`.
    - [x] Verify PHP tests pass without hanging (mocked PDF generation).
    - [x] Commit fixes.

## Next Steps

- [ ] Monitor E2E tests (Dusk) - they might still be slow (generating real PDFs) but shouldn't deadlock the server.
