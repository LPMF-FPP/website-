# Plan: Optimize Label Print Layout (CSS Only)

> **Status:** Ready for Execution
> **Goal:** Improve label printing alignment and density using CSS overrides, without modifying HTML structure.

## 1. File to Modify

**File:** `resources/views/labels/remaining-sheet.blade.php`

## 2. Changes (CSS Append)

We will append the following CSS block to the end of the existing `<style>` tag. This uses `!important` to override existing inline styles or previous classes.

**Key Adjustments:**

- **Margins:** Set print margin to `6mm`.
- **Label Size:** Fixed at `95mm` width. Height set to **42mm** (matching our previous optimization for compactness) or **54mm** (standard 5-row). _I will default to 42mm as per previous context unless instructed otherwise._
- **Padding:** Reduced to `1.5mm` for tighter packing.
- **Typography:** Reduced `line-height` to `1.15`.

**Code Snippet:**

```css
<style>
    /* ... existing styles ... */

    /* =========================
       PRINT OVERRIDES (no new elements)
       ========================= */
    @media print {
        @page { size: A4 portrait; margin: 6mm; }
        body { line-height: 1.15; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .labels-page > table { width: 100%; table-layout: fixed; }
        .labels-page td { padding: 1.5mm !important; vertical-align: top; }
        .label {
            width: 95mm !important;
            height: 42mm !important; /* Optimized for compact layout */
            padding: 1.6mm !important;
            border: 0.25mm solid #333 !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .header-table { margin-bottom: 0.8mm !important; padding-bottom: 0.4mm !important; border-bottom: 0.25mm solid #333 !important; }
        .header-logo { height: 6mm !important; width: auto !important; }
        .label-header h1 { margin: 0 !important; line-height: 1.05 !important; letter-spacing: 0.1pt !important; }
        .label-header .subtitle { margin-top: 0.2mm !important; line-height: 1.05 !important; }
        .field { margin-bottom: 0.6mm !important; }
        .field-label, .field-value { line-height: 1.05 !important; }
        .label-qr img { width: 16mm !important; height: 16mm !important; }
        .qr-text { max-height: 5mm !important; line-height: 1.05 !important; }
        .label-footer { bottom: 1mm !important; padding-top: 0.3mm !important; line-height: 1.05 !important; }
        .page-break { page-break-after: always; break-after: page; }
    }
</style>
```

## 3. Execution Steps

1.  **Update View:** Append CSS to `remaining-sheet.blade.php`.
2.  **Deploy:** Push to GitHub & Update Server.

---

**Ready to execute?** Type "Execute" to apply this CSS patch.
