# Implementation Plan: Precise Label 121 Layout Refactor

> **Status:** Ready for Execution
> **Goal:** Ensure labels align precisely with **Tom & Jerry No. 121** (2×5 on A4) while preserving the Checklist layout, using a deterministic grid.

## 1. Controller Update (`app/Http/Controllers/LabelController.php`)

- **Action:** Revert paper size to standard A4 Portrait (`setPaper('a4', 'portrait')`) for both `remainingSheet` and `remainingForEvidence`. This fixes the checklist page size issue.

## 2. View Refactor (`evidence-sheet.blade.php` & `remaining-sheet.blade.php`)

### A. Data Preparation (Logic Block)

- **Flatten Data:** Convert the `$rows` (Left/Right pairs) into a single flat collection of label items.
- **Pagination:** Chunk the flat collection into groups of 10 (labels per page).

### B. CSS Overhaul (DomPDF Friendly)

- **Global Reset:** `@page { size: A4 portrait; margin: 0; }`.
- **Container (`.sheet`):** Add padding for offset: `padding-top: 2mm; padding-left: 5mm;`.
- **Label Box:** Fixed dimensions: `width: 75mm; height: 38mm;`.
- **Spacer Cells:**
    - Horizontal Gap (`.gap-x`): `width: 5mm`.
    - Vertical Gap (`.gap-y`): `height: 3mm`.
- **Fixes:** Replace `text-wrap` with standard styles. Fix `.clamp2` using `max-height`.

### C. HTML Structure (Deterministic Grid)

Replace the current fluid table with a strict structure:

```html
@foreach($labelPages as $pageItems)
<div class="sheet">
    <table class="grid-table">
        {{-- Loop 5 rows --}} @for($r = 0; $r < 5; $r++)
        <tr>
            {{-- Cell 1 --}}
            <td class="cell">
                @if($item = $pageItems[$r*2] ?? null) @include('label-content',
                ['item' => $item]) @endif
            </td>
            {{-- Gap X --}}
            <td class="gap-x"></td>
            {{-- Cell 2 --}}
            <td class="cell">
                @if($item = $pageItems[$r*2+1] ?? null)
                @include('label-content', ['item' => $item]) @endif
            </td>
        </tr>
        {{-- Gap Y (Between rows only) --}} @if($r < 4)
        <tr class="gap-y">
            <td colspan="3"></td>
        </tr>
        @endif @endfor
    </table>
</div>
<div class="page-break"></div>
@endforeach {{-- Checklist Page (Standard A4) --}}
<div class="checklist-container">...</div>
```

## 3. Execution Steps

1.  **Modify Controller:** Reset paper size.
2.  **Refactor Views:** Implement the new CSS and Grid logic for both label types.
3.  **Deploy:** Update production.

---

**Ready to execute?** Type "Execute" to start.
