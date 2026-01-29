# Implementation Plan: Label Layout Optimization

> **Status:** Ready for Execution
> **Goal:** Menghilangkan _whitespace_ vertikal berlebih pada "Label Sisa" agar tampilan lebih padat dan efisien.

---

### 1. Analisis & Root Cause

- **Observasi:** Terdapat celah kosong sekitar 1.5cm di bagian bawah label (antara QR code dan footer "Dicetak...").
- **Penyebab Teknis:** File `remaining-sheet.blade.php` menggunakan logika _hardcoded height_ yang tidak proporsional dengan isi konten.
    - _Current:_ `$labelHeight = 55mm` (untuk <= 4 label).
    - _Required:_ Konten hanya membutuhkan ~40mm.

### 2. Spesifikasi Perubahan (Code Spec)

Kita akan mengubah logika breakpoint ketinggian label di `resources/views/labels/remaining-sheet.blade.php`:

| Kondisi (Jumlah Label) | Tinggi Lama | Tinggi Baru | Alasan                               |
| :--------------------- | :---------- | :---------- | :----------------------------------- |
| **<= 4**               | 55mm        | **42mm**    | _Primary Fix_ (Mengurangi gap ~13mm) |
| **<= 6**               | 43mm        | **35mm**    | Penyesuaian proporsional             |
| **<= 8**               | 32mm        | **28mm**    | Penyesuaian proporsional             |

### 3. Rencana Deployment (Zero-Risk)

Karena perubahan hanya pada level **View (Blade Template)**, tidak ada risiko terhadap database atau logic bisnis.

**Credentials (Verified from `session-ses_4118`):**

- **Repository:** `https://github.com/LPMF-FPP/website-.git`
- **Auth:** Token `ghp_...` (valid)

**Langkah Eksekusi:**

1.  **Apply Patch:** Update file `remaining-sheet.blade.php` di lokal.
2.  **Commit & Push:** Kirim perubahan ke GitHub menggunakan token.
3.  **Deploy:** SSH ke server (`192.168.0.206`), `git pull`, dan `php artisan view:clear`.

---

**Konfirmasi Akhir:**
Perencanaan sudah matang dan kredensial tersedia.
Ketik **"Eksekusi"** untuk menjalankan langkah 1-3 sekarang.
