# Plan: Precise Label 121 Layout Refactor

> **Status:** Proposal (Read-Only)
> **Goal:** Fix layout issues in Evidence and Remaining labels by implementing a rigid "Label 121" grid template on standard A4 paper, replacing the current fluid/responsive approach.

## 1. Analysis of Current Flaws

- **Double `@page`:** Conflicting page definitions causing unpredictable PDF rendering.
- **Incorrect Dimensions:** `72mm` width (current) vs `75mm` (standard 121).
- **Fluid Grid:** Percentage-based table (`50%`) + padding creates alignment errors.
- **Unsupported CSS:** `text-wrap` and `-webkit-line-clamp` are ignored by DomPDF.
- **Page Size:** Custom paper size breaks the "Checklist" page (which needs A4).

## 2. Technical Specification (Standard 121 on A4)

- **Paper:** A4 Portrait (210mm x 297mm).
- **Page Margins:** Top 2mm, Side 5mm (handled via `@page` or body padding).
- **Grid:** 2 Columns x 5 Rows (10 Labels/Page).
- **Dimensions:**
    - **Label:** 75mm (W) x 38mm (H).
    - **Gap X:** 5mm.
    - **Gap Y:** 3mm.

## 3. Implementation Plan

### A. Controller Cleanup (`LabelController.php`)

- **Revert:** Change `setPaper([...])` back to `setPaper('a4', 'portrait')` for both sheet methods.

### B. View Refactor (`evidence-sheet.blade.php` & `remaining-sheet.blade.php`)

1.  **CSS Reset:**
    - Set single `@page { size: A4; margin: 0; }`.
    - Set body/container padding to handle the start offset (Top 2mm, Left 5mm).
    - Remove unsupported properties (`text-wrap`, etc.).
    - Fix `.clamp2` to use `max-height` + `overflow: hidden`.
2.  **HTML Structure (Fixed Grid):**
    - Replace `chunk(2)` loop with a deterministic row loop.
    - Use explicit spacer cells (`td.gap-x` and `tr.gap-y`).
    - Structure:
        ```html
        <table class="grid-table">
            @foreach($chunkedRows as $row)
            <tr>
                <td class="cell">Label 1</td>
                <td class="gap-x"></td>
                <td class="cell">Label 2</td>
            </tr>
            <tr class="gap-y">
                <td colspan="3"></td>
            </tr>
            @endforeach
        </table>
        ```
3.  **Content Restoration:**
    - Ensure "Barang Bukti" (Left) and "LPMF" (Right) titles are correct.
    - Ensure Subtitle is present.

### C. QR Code

- **Confirmed:** QR is Base64 Data URI (`$unit->qr_png`), compatible with DomPDF.

## 4. Execution Steps

1.  **Modify Controller:** Revert paper size to A4.
2.  **Refactor Views:** Apply the fixed grid layout and cleaned CSS to both sheets.
3.  **Deploy:** Update production.

---

**Ready to execute?** Type "Execute" to start the refactor.
