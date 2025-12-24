# Cara Menggunakan Fitur Hapus Banyak File

## Lokasi
**Settings** → **Manajemen Dokumen**

## Fitur Baru

### ✅ Checkbox untuk Setiap File
Setiap baris dokumen sekarang memiliki checkbox di kolom paling kiri.

### ✅ Pilih Semua
Checkbox di header table untuk memilih/membatalkan semua dokumen di halaman saat ini.

### ✅ Toolbar Aksi Bulk
Muncul otomatis saat ada dokumen yang dipilih, menampilkan:
- Jumlah dokumen yang dipilih
- Tombol "Hapus Terpilih" (merah)
- Tombol "Batal"

## Langkah Penggunaan

### 1. Pilih Dokumen

**Opsi A: Pilih Individual**
- Klik checkbox di sebelah kiri nama file
- File yang dipilih akan ter-highlight (background biru muda)

**Opsi B: Pilih Semua**
- Klik checkbox di header table (paling atas)
- Semua file di halaman ini akan dipilih

### 2. Hapus yang Dipilih

1. Setelah memilih file, toolbar muncul di atas table
2. Klik tombol **"Hapus Terpilih"** (merah)
3. Konfirmasi dengan klik **OK** di pop-up
4. Tunggu proses selesai

### 3. Lihat Hasil

Setelah selesai, akan muncul pesan:
- ✅ **Hijau**: "X dokumen berhasil dihapus"
- ⚠️ **Kuning**: "X dokumen berhasil dihapus. Y gagal"
- ❌ **Merah**: Detail error jika ada yang gagal

## Tips

- **Gunakan Filter**: Sempitkan daftar dengan filter sebelum memilih
- **Per Halaman**: Pilih dan hapus per halaman (10/25/50 items)
- **Batal Memilih**: Klik tombol "Batal" atau checkbox header untuk unselect
- **Loading State**: Tombol disabled saat proses delete berjalan

## Catatan Penting

⚠️ **Tidak ada undo!** File yang dihapus tidak bisa dikembalikan.  
⚠️ **Konfirmasi**: Selalu muncul konfirmasi sebelum menghapus.  
⚠️ **Sequential**: File dihapus satu per satu untuk keamanan.

---

**Screenshot Fitur**:
```
┌─────────────────────────────────────────────────┐
│ ✓ 3 dokumen dipilih     [Hapus Terpilih] [Batal] │ ← Toolbar Bulk
├─────────────────────────────────────────────────┤
│ ☑ [✓] Nama File              │ Tipe │ Ukuran  │ ← Header
│ ☐ [ ] file1.pdf              │ LHU  │ 2.3 MB  │ ← Row
│ ☑ [✓] file2.pdf              │ BA   │ 1.5 MB  │
│ ☑ [✓] file3.pdf              │ SPT  │ 890 KB  │
└─────────────────────────────────────────────────┘
```
