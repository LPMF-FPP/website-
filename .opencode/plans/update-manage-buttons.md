# Plan: Standardize Button Styles (UI Skills)

> **Status:** Proposal (Read-Only)
> **Goal:** Update the button styles on the `manage.blade.php` page to match the application's standard design (White/Gray Border buttons with Icons), and fix UI/UX violations identified by `/ui-skills`.

## 1. Analysis

- **Current State:** Buttons (Edit, Nonaktifkan, Hapus) are plain text links.
- **Target State:** Buttons should use the standard "secondary action" style found in `index.blade.php`.
- **Target Classes:** `inline-flex items-center px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500` (plus variations for text color).

## 2. Implementation Details

### A. Button Styling (resources/views/monitoring/environment/manage.blade.php)

Replace the existing text buttons inside the loop with the following structures:

**1. Edit Button:**

- **Style:** Standard White/Gray.
- **Icon:** Pencil Square (`heroicon-o-pencil-square`).
- **Classes:** `text-gray-700 bg-white border border-gray-300 hover:bg-gray-50`.

**2. Toggle Active Button:**

- **Style:** Standard White/Gray.
- **Icon:** Power (`heroicon-o-power`) or Check/X Circle.
- **Logic:**
    - _Active:_ Text "Nonaktifkan", Icon `power`, Color `text-yellow-600`.
    - _Inactive:_ Text "Aktifkan", Icon `power`, Color `text-green-600`.

**3. Delete Button:**

- **Style:** Danger Outline.
- **Icon:** Trash (`heroicon-o-trash`).
- **Classes:** `text-red-700 bg-white border border-gray-300 hover:bg-red-50 hover:border-red-300`.

### B. UI/UX Fixes (from /ui-skills)

1.  **Animation Speed:** Change all `duration-300` to `duration-200` in modal transitions.
2.  **Typography:** Add `text-balance` to headings (e.g., "Daftar Lokasi").
3.  **Sizing:** Replace `w-4 h-4` with `size-4` where appropriate (if Tailwind config supports it, otherwise stick to standard w/h to avoid breaking changes if `size-` isn't generated). _Self-correction: I will stick to `w-4 h-4` to be safe unless I confirm `size-` utility exists._

## 3. Execution Steps

1.  **Edit View:** Modify `resources/views/monitoring/environment/manage.blade.php` to apply new button classes and icons.
2.  **Edit View:** Search and replace `duration-300` with `duration-200`.
3.  **Deploy:** Copy the updated file to production.
4.  **Clear Cache:** Run `view:clear` on production.

---

**Ready to execute?** Type "Execute" to apply the changes.
