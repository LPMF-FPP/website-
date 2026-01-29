# Implementation Plan: Label 121 Format (Sheet & Evidence)

> **Status:** Proposal (Read-Only)
> **Goal:** Configure the system to generate PDF labels compatible with **Label 121** (Custom Paper Size 16.2cm x 20.5cm, 10 labels/sheet).

## 1. Analysis

- **Target Format:** "Label 121" (Standard sticker format).
- **Dimensions:**
    - **Paper Size:** 162mm (width) x 205mm (height). This is a custom size, not standard A4.
    - **Grid:** 2 Columns, 5 Rows (Total 10 labels).
    - **Label Size:** 77mm x 38mm.
    - **Margins:** Top 2mm (0.2cm), Side 5mm (0.5cm).
- **Current State:** Uses A4 Portrait. Dynamic label sizing based on count.
- **Impact:** Changes `LabelController` (PDF setup) and Blade Views (CSS layout).

## 2. Implementation Steps

### Phase 1: Controller Update (`app/Http/Controllers/LabelController.php`)

Modify `remainingSheet` and `remainingForEvidence` methods to use the custom paper size.

- **Change:** Replace `setPaper('a4', 'portrait')` with:
    ```php
    // 16.2cm x 20.5cm converted to points (1cm = 28.3465pt)
    // Width: 459.21 pt, Height: 581.10 pt
    $pdf->setPaper([0, 0, 459.21, 581.10], 'portrait');
    ```

### Phase 2: View Update (`resources/views/labels/remaining-sheet.blade.php` & `evidence-sheet.blade.php`)

Update the CSS to match the physical dimensions of Label 121.

- **Page Margins:**
    ```css
    @page {
        margin: 2mm 5mm;
        size: 162mm 205mm;
    }
    ```
- **Label Container:**
    ```css
    .label {
        width: 77mm !important;
        height: 38mm !important;
        margin-right: 3mm; /* Gap compensation */
        margin-bottom: 3mm; /* Gap compensation */
        /* ... existing styles ... */
    }
    ```
- **Grid Logic:** Ensure the loop chunks by 2 (already done) and the table layout respects the fixed widths.

## 3. Verification

- **Print Test:** User should print the generated PDF on actual Label 121 paper to verify alignment.
- **Scaling:** Ensure printer settings are set to "Actual Size" (Scale 100%) to avoid misalignment.

---

**Ready to execute?** Type "Execute" to apply changes.
