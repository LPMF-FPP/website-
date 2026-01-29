# Plan: Optimize Label Sizes & Remove Header Border

> **Status:** Ready for Execution
> **Goal:** Remove the header separator line and fill the empty space by increasing the size of logos, fonts, and the QR code, rather than just adding whitespace.

## 1. CSS Modifications

**File:** `resources/views/labels/remaining-sheet.blade.php`

### A. Remove Header Border

- **Target:** `.header-table`
- **Action:** Set `border-bottom: none !important;`.

### B. Upsize Elements (Proportional Optimization)

We will increase dimensions to fill the 38mm height naturally.

| Element                 | Current Size | New Size |
| :---------------------- | :----------- | :------- |
| **Logo**                | 5mm          | **6mm**  |
| **Header Title (SISA)** | 7pt          | **9pt**  |
| **Subtitle**            | 4pt          | **5pt**  |
| **Field Label**         | 5pt          | **6pt**  |
| **Field Value**         | 6pt          | **8pt**  |
| **QR Code**             | 12mm         | **15mm** |
| **Footer**              | 4pt          | **5pt**  |

### C. Footer Border

- **Constraint:** Ensure `.label-footer` retains `border-top: 1px dotted ...`.

## 2. Execution Steps

1.  **Edit View:** Apply the CSS updates to the style block.
2.  **Deploy:** Update production.
3.  **Clear Cache:** Force view refresh.

---

**Ready to execute?** Type "Execute" to apply changes.
