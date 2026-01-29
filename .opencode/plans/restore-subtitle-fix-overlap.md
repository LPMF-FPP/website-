# Plan: Restore Subtitle & Fix Overlap

> **Status:** Ready for Execution
> **Goal:** Restore the header subtitle on both label types and fix the layout overlap caused by increased element sizes.

## 1. CSS Adjustments (Tightening Layout)

**Files:** `remaining-sheet.blade.php` & `evidence-sheet.blade.php`

To fit the subtitle back into the 38mm height:

- **Logo Height:** Reduce `7mm` -> **5mm**.
- **Header Title:** Reduce `9pt` -> **8pt**, margin bottom `1mm` -> **0.2mm**.
- **Subtitle:** Restore display, set font **5pt**, margin top **0.1mm**.
- **Field Spacing:** Reduce `margin-bottom` `0.8mm` -> **0.4mm**.
- **Field Value:** Reduce font `8pt` -> **7pt**.

## 2. HTML Adjustments (Restoring Content)

**Files:** `remaining-sheet.blade.php` & `evidence-sheet.blade.php`

- Re-insert the subtitle `div` inside the header table cell.
    - Text: "LPMF - Laboratorium Farmapol Pusdokkes Polri" (Remaining) / "Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri" (Evidence).

## 3. Execution Steps

1.  **Edit `remaining-sheet.blade.php`:** Update CSS block and HTML structure.
2.  **Edit `evidence-sheet.blade.php`:** Update CSS block and HTML structure.
3.  **Deploy:** Update production files.
4.  **Clear Cache:** Force view refresh.

---

**Ready to execute?** Type "Execute" to apply.
