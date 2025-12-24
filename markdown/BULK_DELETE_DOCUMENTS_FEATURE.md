# Bulk Delete Documents Feature

## Fitur Baru: Checkbox untuk Menghapus Banyak File Sekaligus

### Deskripsi
Menambahkan kemampuan untuk memilih dan menghapus beberapa dokumen sekaligus di halaman Settings > Manajemen Dokumen.

### Fitur yang Ditambahkan

#### 1. **Multi-Select State Management**
File: `resources/js/pages/settings/index.js`

State baru:
- `selectedDocuments: []` - Array path dokumen yang dipilih
- `bulkDeleteLoading: false` - Status loading untuk bulk delete

#### 2. **Methods untuk Multi-Select**

**`toggleDocumentSelection(path)`**
- Toggle selection untuk satu dokumen

**`toggleAllDocuments()`**
- Pilih/unselect semua dokumen di halaman saat ini

**`isDocumentSelected(path)`**
- Check apakah dokumen sudah dipilih

**`hasSelectedDocuments`** (getter)
- Return true jika ada dokumen yang dipilih

**`allDocumentsSelected`** (getter)
- Return true jika semua dokumen di halaman ini dipilih

**`bulkDeleteDocuments()`**
- Hapus semua dokumen yang dipilih
- Konfirmasi sebelum menghapus
- Menampilkan progress dan hasil (success/fail)
- Error handling per-dokumen

#### 3. **UI Components**

**Checkbox di Table Header**
```html
<input type="checkbox" 
       :checked="client.allDocumentsSelected"
       @change="client.toggleAllDocuments()"
       title="Pilih semua">
```

**Checkbox di Setiap Row**
```html
<input type="checkbox"
       :checked="client.isDocumentSelected(entry.path)"
       @change="client.toggleDocumentSelection(entry.path)">
```

**Bulk Action Toolbar**
Muncul otomatis ketika ada dokumen yang dipilih:
- Menampilkan jumlah dokumen yang dipilih
- Tombol "Hapus Terpilih" (merah)
- Tombol "Batal" untuk clear selection
- Status loading saat proses delete

**Visual Feedback**
- Row yang dipilih diberi background `bg-blue-50`
- Toolbar bulk action dengan background `bg-blue-50`

### Cara Menggunakan

1. **Pilih Dokumen Individual**
   - Klik checkbox di sebelah kiri setiap dokumen
   - Dokumen yang dipilih akan ter-highlight (background biru muda)

2. **Pilih Semua Dokumen**
   - Klik checkbox di header table
   - Semua dokumen di halaman saat ini akan dipilih

3. **Hapus Dokumen yang Dipilih**
   - Setelah memilih dokumen, toolbar bulk action muncul di atas table
   - Klik tombol "Hapus Terpilih"
   - Konfirmasi pop-up akan muncul
   - Proses delete berjalan dan menampilkan hasil

4. **Batal Memilih**
   - Klik tombol "Batal" di toolbar
   - Atau klik checkbox individual untuk unselect
   - Atau klik checkbox header untuk unselect semua

### Error Handling

- Jika ada dokumen yang gagal dihapus, akan ditampilkan error message
- Success dan fail count ditampilkan setelah proses selesai
- Status message dengan warna:
  - Hijau (emerald): Semua berhasil
  - Kuning (amber): Sebagian berhasil, sebagian gagal
  - Merah: Error detail per dokumen

### Technical Details

**Backend**
- Menggunakan endpoint existing: `DELETE /api/settings/documents`
- Bulk delete dilakukan dengan loop requests (sequential)
- Setiap request independen untuk error isolation

**Frontend**
- Alpine.js reactive state untuk checkbox management
- Automatic UI updates dengan Alpine directives
- Responsive dan mobile-friendly

**Performance**
- Bulk delete dengan sequential requests untuk reliability
- Loading state mencegah duplicate requests
- Pagination preserved after delete

### Files Modified

1. `resources/js/pages/settings/index.js`
   - Added multi-select state
   - Added bulk delete methods
   - Added selection management methods

2. `resources/views/settings/partials/documents.blade.php`
   - Added checkboxes to table
   - Added bulk action toolbar
   - Added visual feedback for selection

3. `public/build/assets/*`
   - Rebuilt frontend assets

### Testing

1. Navigate to `/settings` → Tab "Manajemen Dokumen"
2. Verify checkboxes appear in table
3. Test selecting individual documents
4. Test "select all" checkbox
5. Test bulk delete with multiple documents
6. Verify success/error messages
7. Verify table refreshes after delete

### Future Improvements

- Add keyboard shortcuts (Shift+Click for range select)
- Add filter to only show selected documents
- Add export selected documents feature
- Batch API endpoint untuk bulk delete (single request)
- Add undo/restore deleted documents

---

**Status**: ✅ Implemented and tested  
**Build**: Assets rebuilt successfully  
**Version**: Compatible with Laravel 12 + Alpine.js
