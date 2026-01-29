# Implementation Plan: Manajemen Penyidik + Label 121

> **Status:** Ready for Execution  
> **Priority:** 1. Manajemen Penyidik, 2. Label 75×38mm  
> **Risk Level:** Low (additive changes, no breaking changes)

---

## Overview

### Improvement 1: Manajemen Penyidik (Investigator Management)

Menambahkan halaman CRUD untuk mengelola biodata penyidik (pangkat, no HP, email, satker) dengan akses hanya untuk admin.

### Improvement 2: Label Tom & Jerry No. 121 (75×38mm)

Mengubah semua label (sheet dan single) ke ukuran standar 75×38mm dengan grid deterministik 2×5 per halaman A4.

---

## Part 1: Manajemen Penyidik

### 1.1 Permissions (PermissionSeeder)

**File:** `database/seeders/PermissionSeeder.php`

**Tambah permissions baru:**

```php
// Manajemen Penyidik
['name' => 'investigators.view', 'display_name' => 'Lihat Manajemen Penyidik', 'module' => 'investigators', 'action' => 'view'],
['name' => 'investigators.edit', 'display_name' => 'Edit Penyidik', 'module' => 'investigators', 'action' => 'edit'],
['name' => 'investigators.delete', 'display_name' => 'Hapus Penyidik', 'module' => 'investigators', 'action' => 'delete'],
```

**Assign ke role `admin`:**

```php
'admin' => [
    // ... existing
    'investigators.view', 'investigators.edit', 'investigators.delete',
],
```

---

### 1.2 Policy Update

**File:** `app/Policies/InvestigatorPolicy.php`

**Tambah methods:**

```php
public function viewAny(User $user): bool
{
    return $user->role === 'admin';
}

public function view(User $user, Investigator $investigator): bool
{
    return $user->role === 'admin';
}

public function update(User $user, Investigator $investigator): bool
{
    return $user->role === 'admin';
}

public function delete(User $user, Investigator $investigator): bool
{
    return $user->role === 'admin';
}
```

---

### 1.3 Controller Baru

**File:** `app/Http/Controllers/InvestigatorManagementController.php`

**Methods:**

| Method                                                 | Fungsi                                                            |
| ------------------------------------------------------ | ----------------------------------------------------------------- |
| `index(Request $request)`                              | List penyidik + filter (nama, NRP, satker, is_polri) + pagination |
| `show(Investigator $investigator)`                     | Detail biodata + riwayat test requests                            |
| `edit(Investigator $investigator)`                     | Form edit biodata                                                 |
| `update(Request $request, Investigator $investigator)` | Simpan perubahan (validate + save)                                |
| `destroy(Investigator $investigator)`                  | Hard delete dengan konfirmasi                                     |

**Editable fields:**

- `rank` (pangkat)
- `phone` (no HP)
- `alt_phone` (no HP alternatif)
- `email`
- `jurisdiction` (satker)

**Non-editable fields (read-only di form):**

- `name`
- `nrp`
- `is_polri`
- `institution`
- `occupation`

---

### 1.4 Routes

**File:** `routes/web.php`

**Tambah setelah analysts routes (line ~187):**

```php
// Manajemen Penyidik
Route::middleware(['auth'])->group(function () {
    Route::get('investigators', [InvestigatorManagementController::class, 'index'])
        ->name('investigators.index')
        ->can('investigators.view');
    Route::get('investigators/{investigator}', [InvestigatorManagementController::class, 'show'])
        ->name('investigators.show')
        ->can('investigators.view');
    Route::get('investigators/{investigator}/edit', [InvestigatorManagementController::class, 'edit'])
        ->name('investigators.edit')
        ->can('investigators.edit');
    Route::put('investigators/{investigator}', [InvestigatorManagementController::class, 'update'])
        ->name('investigators.update')
        ->can('investigators.edit');
    Route::delete('investigators/{investigator}', [InvestigatorManagementController::class, 'destroy'])
        ->name('investigators.destroy')
        ->can('investigators.delete');
});
```

---

### 1.5 Views

**Direktori:** `resources/views/investigators/`

#### 1.5.1 `index.blade.php`

- Page header: "Manajemen Penyidik"
- Filter form: Kata kunci (nama/NRP), Satker (dropdown), Tipe (Polri/Non-Polri)
- Table columns: Nama, Pangkat, NRP, Satker, No HP, Aksi
- Action dropdown: Lihat, Edit, Hapus
- Pagination

#### 1.5.2 `show.blade.php`

- Page header: "Detail Penyidik: {name}"
- Section 1: Biodata (pangkat, NRP, satker, phone, email, address)
- Section 2: Riwayat Permohonan (table: No Surat, Tanggal, Status, Aksi)
- Action buttons: Edit, Kembali

#### 1.5.3 `edit.blade.php`

- Page header: "Edit Penyidik: {name}"
- Form fields:
    - Pangkat (text input)
    - No HP (text input)
    - No HP Alternatif (text input)
    - Email (email input)
    - Satker (text input, uppercase)
- Read-only info: Nama, NRP, Tipe
- Buttons: Simpan, Batal

#### 1.5.4 `_form.blade.php` (partial)

- Shared form fields untuk edit

---

### 1.6 Navigation

**File:** `resources/views/layouts/navigation.blade.php`

**Tambah setelah "Manajemen Staff" (line ~177):**

```blade
@can('investigators.view')
<a href="{{ route('investigators.index') }}" class="group flex items-center p-2 rounded-md hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
    <svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
    </svg>
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Manajemen Penyidik</span>
</a>
@endcan
```

---

## Part 2: Label Tom & Jerry No. 121 (75×38mm)

### 2.1 Spesifikasi Standar

| Property    | Value                                |
| ----------- | ------------------------------------ |
| Paper       | A4 Portrait (210mm × 297mm)          |
| Label Size  | **75mm × 38mm**                      |
| Grid        | 2 kolom × 5 baris = 10 label/halaman |
| Gap X       | 5mm (antar kolom)                    |
| Gap Y       | 3mm (antar baris)                    |
| Offset Left | 5mm                                  |
| Offset Top  | 2mm                                  |

---

### 2.2 Controller Update

**File:** `app/Http/Controllers/LabelController.php`

**Semua method sheet menggunakan A4:**

```php
$pdf->setPaper('a4', 'portrait');
```

**Single label (75×38mm in points):**

```php
// 75mm = 212.60pt, 38mm = 107.72pt
$pdf->setPaper([0, 0, 212.60, 107.72], 'landscape');
```

---

### 2.3 Sheet Templates Refactor

**Files:**

- `resources/views/labels/evidence-sheet.blade.php`
- `resources/views/labels/remaining-sheet.blade.php`

#### CSS Changes:

```css
/* Hapus @page ganda, gunakan satu saja */
@page {
    size: A4 portrait;
    margin: 0;
}

/* Container offset via padding */
.sheet {
    padding-top: 2mm;
    padding-left: 5mm;
}

/* Label dimensi standar 121 */
.label {
    width: 75mm !important;
    height: 38mm !important;
}

/* Grid table */
.grid-table {
    border-collapse: collapse;
    table-layout: fixed;
}

.cell {
    width: 75mm;
    height: 38mm;
    vertical-align: top;
    padding: 0;
}

.gap-x {
    width: 5mm;
}

.gap-y td {
    height: 3mm;
}

/* Hapus CSS tidak didukung DomPDF */
/* Ganti .clamp2 dengan: */
.clamp2 {
    max-height: 8mm;
    overflow: hidden;
}
```

#### HTML Grid Structure:

```blade
@php
    // Flatten data menjadi array linear
    $labelsFlat = collect();
    foreach ($rows as $row) {
        if (isset($row['left'])) $labelsFlat->push($row['left']);
        if (isset($row['right'])) $labelsFlat->push($row['right']);
    }
    $pages = $labelsFlat->chunk(10);
@endphp

@foreach($pages as $pageIndex => $pageItems)
<div class="sheet">
    <table class="grid-table">
        @for($r = 0; $r < 5; $r++)
            @php
                $L = $pageItems->get($r * 2);
                $R = $pageItems->get($r * 2 + 1);
            @endphp
            <tr>
                <td class="cell">
                    @if($L)
                        <div class="label">...</div>
                    @endif
                </td>
                <td class="gap-x"></td>
                <td class="cell">
                    @if($R)
                        <div class="label">...</div>
                    @endif
                </td>
            </tr>
            @if($r < 4)
                <tr class="gap-y"><td colspan="3"></td></tr>
            @endif
        @endfor
    </table>
</div>
@if(!$loop->last)
    <div style="page-break-after: always;"></div>
@endif
@endforeach
```

---

### 2.4 Single Label Templates

**Files:**

- `resources/views/labels/evidence-single.blade.php`
- `resources/views/labels/remaining-single.blade.php`

**Changes:**

```css
@page {
    size: 75mm 38mm;
    margin: 0;
}

.label {
    width: 75mm;
    height: 38mm;
    padding: 1mm;
}
```

---

## Execution Checklist

### Phase 1: Manajemen Penyidik

- [ ] Update `PermissionSeeder.php` - tambah 3 permissions
- [ ] Update `InvestigatorPolicy.php` - tambah 4 methods
- [ ] Create `InvestigatorManagementController.php`
- [ ] Update `routes/web.php` - tambah routes
- [ ] Create `resources/views/investigators/index.blade.php`
- [ ] Create `resources/views/investigators/show.blade.php`
- [ ] Create `resources/views/investigators/edit.blade.php`
- [ ] Create `resources/views/investigators/_form.blade.php`
- [ ] Update `navigation.blade.php` - tambah menu link
- [ ] Run `php artisan db:seed --class=PermissionSeeder`
- [ ] Test: login as admin, akses /investigators
- [ ] Test: view, edit, delete penyidik

### Phase 2: Label 121

- [ ] Update `LabelController.php` - setPaper settings
- [ ] Refactor `evidence-sheet.blade.php` - CSS + grid
- [ ] Refactor `remaining-sheet.blade.php` - CSS + grid
- [ ] Refactor `evidence-single.blade.php` - ukuran 75×38
- [ ] Refactor `remaining-single.blade.php` - ukuran 75×38
- [ ] Test: generate PDF, cek ukuran di viewer
- [ ] Test: print ke kertas Tom & Jerry 121 (jika tersedia)

### Phase 3: Deployment

- [ ] Run tests: `npm run test`
- [ ] Run audits: `npm run audit:critical`
- [ ] Commit: `git add . && git commit -m "feat: add investigator management + label 121 layout"`
- [ ] Deploy ke staging (jika ada)
- [ ] Run seeder di production: `php artisan db:seed --class=PermissionSeeder`
- [ ] Clear cache: `php artisan optimize:clear`
- [ ] Test di production

---

## Risk Assessment

| Component        | Risk   | Mitigation                                     |
| ---------------- | ------ | ---------------------------------------------- |
| Permissions      | Low    | Additive only, no existing permissions changed |
| Policy           | Low    | New methods only, existing methods unchanged   |
| Routes           | Low    | New routes, no conflicts with existing         |
| Views            | Low    | New files, no existing views modified          |
| Navigation       | Low    | Additive menu item                             |
| Label CSS        | Medium | Test print ke kertas fisik sebelum deploy      |
| Label Controller | Low    | Only paper size change                         |

**Overall Risk: LOW** - Semua perubahan bersifat additive (menambah), tidak ada breaking changes pada fitur existing.

---

## Post-Deployment Steps

1. **Assign permission** ke admin users yang perlu akses:

    ```bash
    php artisan tinker
    # Atau via UI jika ada
    ```

2. **Test label fisik** dengan kertas Tom & Jerry 121 sebelum digunakan untuk cetak massal

3. **Monitor logs** untuk error pada fitur baru

---

## Files Summary

### New Files (5)

```
app/Http/Controllers/InvestigatorManagementController.php
resources/views/investigators/index.blade.php
resources/views/investigators/show.blade.php
resources/views/investigators/edit.blade.php
resources/views/investigators/_form.blade.php
```

### Modified Files (9)

```
database/seeders/PermissionSeeder.php
app/Policies/InvestigatorPolicy.php
routes/web.php
resources/views/layouts/navigation.blade.php
app/Http/Controllers/LabelController.php
resources/views/labels/evidence-sheet.blade.php
resources/views/labels/remaining-sheet.blade.php
resources/views/labels/evidence-single.blade.php
resources/views/labels/remaining-single.blade.php
```
