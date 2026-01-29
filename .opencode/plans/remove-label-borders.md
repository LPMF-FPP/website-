# Plan: Remove Label Borders & Optimize Layout

> **Status:** Ready for Execution
> **Goal:** Remove the horizontal separator lines (Header Bottom & Footer Top) and optimize vertical spacing to fill the label proportionally.

## 1. CSS Modifications

**File:** `resources/views/labels/remaining-sheet.blade.php`

### A. Remove Borders

1.  **Header:** Set `.header-table { border-bottom: none !important; }`.
2.  **Footer:** Set `.label-footer { border-top: none !important; }`.

### B. Optimize Spacing (Proportionality)

Since lines are removed, we use whitespace to separate sections and fill the "empty space" at the bottom.

1.  **Header Spacing:**
    - Increase `margin-bottom` of header slightly to separate from body.
2.  **Body Spacing:**
    - Increase `.field` `margin-bottom` from `0.6mm` to `1.5mm`.
    - This spreads the data rows (Resi, Kode, Tgl, Qty) to occupy the full height of the label, eliminating the empty gap at the bottom.
3.  **QR Code:**
    - Slightly increase QR size (`12mm` -> `14mm`) to match the taller content area.

## 2. Execution Steps

1.  **Edit View:** Apply CSS changes.
2.  **Deploy:** Update production server.
3.  **Clear Cache:** Ensure changes are rendered.

---

**Ready to execute?** Type "Execute" to apply.
