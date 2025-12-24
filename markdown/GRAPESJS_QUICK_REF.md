# Quick Reference: Template Editor GrapesJS

## 🚀 Cara Menggunakan Template Editor

### 1. Akses Halaman Template
```
URL: http://your-domain.com/templates
Login: Admin/Superadmin
```

### 2. Buat Template Baru
1. Klik tombol **"+ Buat Template Baru"**
2. Isi form:
   - **Nama Template:** Contoh: "Template BA Penerimaan Sampel V2"
   - **Tipe Dokumen:** Pilih BA atau LHU
3. Klik **"Buat & Edit"**
4. Otomatis redirect ke editor

### 3. Edit Template di GrapesJS
1. **Drag & Drop Components:**
   - Drag blok dari sidebar kiri (Section, Text, Image, Table)
   - Drop ke canvas tengah
2. **Edit Content:**
   - Klik component → edit langsung di canvas
   - atau gunakan sidebar kanan untuk properties
3. **Gunakan Token:**
   - Ketik `{{request_number}}` di text
   - Token akan diganti otomatis saat generate PDF
   - Lihat daftar token di bawah editor

### 4. Save Template
- Klik **"💾 Save Draft"** → simpan perubahan
- Status tetap "draft" sampai di-issue

### 5. Preview PDF
- Klik **"👁️ Preview PDF"** → buka preview PDF di tab baru
- Preview menggunakan sample data
- Token akan diganti dengan contoh nilai

### 6. Issue Template
- Klik **"✓ Issue"** → konfirmasi
- Template menjadi "issued" (final, tidak bisa diubah status)
- Pastikan sudah di-review sebelum issue!

### 7. Activate Template
- Klik **"⚡ Activate"** → konfirmasi
- Template menjadi aktif untuk doc_type tersebut
- Template lain dengan doc_type sama otomatis non-aktif

---

## 📋 Token yang Tersedia

Gunakan token berikut dalam template (case-sensitive):

### General
- `{{request_number}}` → Nomor permintaan (REQ-2025-0001)
- `{{requester_name}}` → Nama pemohon
- `{{agency_name}}` → Nama instansi
- `{{case_number}}` → Nomor perkara (LP/001/I/2025)

### Sample Info
- `{{sample_count}}` → Jumlah sampel
- `{{sample_types}}` → Jenis sampel
- `{{sample_description}}` → Deskripsi sampel

### Test Info (LHU)
- `{{test_date}}` → Tanggal uji
- `{{analyst_name}}` → Nama analis
- `{{test_method}}` → Metode uji
- `{{test_results}}` → Hasil uji
- `{{conclusion}}` → Kesimpulan

### Lab Info
- `{{lab_name}}` → Nama laboratorium
- `{{lab_address}}` → Alamat lab

**Catatan:** Token yang tidak ada di whitelist tidak akan diganti (tetap {{token}})

---

## 🎨 Tips Design Template

### Layout A4
- **Width:** 21 cm (210mm)
- **Height:** 29.7 cm (297mm)
- **Margin:** 10mm (default)
- Set di style manager: width: 100%, max-width: 21cm

### Typography
- **Heading 1:** 18pt, bold
- **Heading 2:** 16pt, bold
- **Body:** 12pt, regular
- **Small:** 10pt

### Spacing
- **Padding:** Gunakan 10px, 15px, 20px (kelipatan 5)
- **Margin:** Gunakan margin-top/bottom untuk spacing

### Table
- **Border:** 1px solid #ddd
- **Padding:** 8px untuk cell
- **Background:** Alternating rows (bg-gray-50)

### Colors (Recommended)
- **Primary:** #1e40af (blue-800)
- **Text:** #111827 (gray-900)
- **Border:** #d1d5db (gray-300)
- **Background:** #f9fafb (gray-50)

---

## 🔍 Filter & Search

### Filter Template
- **Doc Type:** BA / LHU / Semua
- **Status:** draft / issued / obsolete / Semua
- **Active:** Aktif / Tidak Aktif / Semua

Filter langsung update tabel tanpa reload halaman.

---

## ⚠️ Common Issues

### 1. Token Tidak Diganti
**Problem:** Token masih muncul sebagai {{token}} di PDF  
**Solution:**
- Pastikan token exact match (case-sensitive)
- Cek whitelist token di bawah editor
- Token harus format: `{{token_name}}` (2 kurung kurawal)

### 2. PDF Layout Rusak
**Problem:** Layout berbeda di PDF vs editor  
**Solution:**
- Gunakan fixed width (21cm untuk A4)
- Hindari % width untuk print
- Test preview sebelum issue

### 3. Image Tidak Muncul
**Problem:** Image tidak tampil di PDF  
**Solution:**
- Gunakan absolute URL untuk image
- Atau base64 encoded image
- Avoid external CDN yang require auth

### 4. Save Draft Gagal
**Problem:** Error saat save  
**Solution:**
- Check koneksi internet
- Reload halaman dan coba lagi
- Check browser console untuk error detail

---

## 🔐 Permissions

**Required:** `manage-settings` gate

**Roles yang bisa akses:**
- Admin
- Superadmin

**Tidak bisa akses:**
- Analyst
- Staff biasa
- Guest

---

## 📱 Device Preview

GrapesJS editor support 3 device preview:

1. **Desktop** (default) → 100% width
2. **Tablet** → 768px width
3. **Mobile** → 375px width

**Catatan:** PDF selalu A4 (21cm), device preview hanya untuk design reference.

---

## 🛠️ Troubleshooting

### Halaman Blank
1. Hard refresh: Ctrl+Shift+R (Windows) / Cmd+Shift+R (Mac)
2. Clear browser cache
3. Check console untuk error

### GrapesJS Tidak Load
1. Check koneksi internet (CDN untuk CSS)
2. Reload halaman
3. Contact admin jika persist

### 419 Error (CSRF)
1. Reload halaman
2. Login ulang
3. Check session timeout (2 jam)

---

## 📞 Support

**Contact:** IT Support  
**Docs:** `/docs/grapesjs-ui-implementation.md`  
**API Docs:** `/docs/template-editor-api.md`

---

**Last Updated:** December 22, 2025
