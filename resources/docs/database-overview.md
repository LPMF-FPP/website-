# Ringkasan Database Farmapol

Dokumen ini memberikan gambaran cepat mengenai tabel kunci, relasi utama, serta catatan operasional untuk basis data Farmapol Pusdokkes Polri.

## Tabel Inti

- **test_requests**  
  Menyimpan data permintaan pengujian obat/bahan. Relasi utama:
  - `hasMany` **samples**
  - `belongsTo` **users** (investigator/pemohon)

- **samples**  
  Detil setiap sampel yang diterima dari permintaan.
  - `belongsTo` **test_requests**
  - `hasMany` **sample_test_processes**
  - `hasMany` lampiran hasil uji (melalui relasi file / laporan)

- **sample_test_processes**  
  Melacak tahapan workflow pengujian mulai dari preparasi, instrumentasi, hingga interpretasi hasil.
  - Menyimpan status per tahap serta catatan analis.

- **deliveries**  
  Riwayat serah terima hasil uji kepada pemohon, termasuk berita acara dan survey kepuasan.

- **users**  
  Akun sistem (admin, supervisor, analis, petugas lab, investigator). Relasi ke permintaan, proses, dan audit log.

## Migrasi dan Seeding

- Jalankan `php artisan migrate --seed` pada lingkungan baru untuk memuat struktur dan data referensi dasar (roles, metode uji, unit satuan, dll).
- Seeder khusus:
  - `Database\Seeders\RolePermissionSeeder`
  - `Database\Seeders\TestMethodSeeder`

## Pemeliharaan

- **Backup**: disarankan jadwal harian menggunakan dump database (contoh `mysqldump` atau schedule bawaan server).
- **Monitoring**: pantau ukuran tabel `samples` dan `sample_test_processes` karena tumbuh paling cepat.
- **Indexing**: pastikan kolom `status`, `test_request_id`, dan `assigned_analyst_id` memiliki indeks untuk performa optimal pada dashboard dan tracking.

## Catatan Operasional

- Setiap perubahan status sampel harus dilakukan melalui workflow controller untuk menjaga konsistensi log.
- Jika ada perubahan struktur, perbarui dokumentasi ini serta buat migrasi baru; hindari perubahan langsung pada database produksi.
- Simpan laporan HTML pada direktori `output/laporan-hasil-uji` agar dapat diakses melalui route `laporan-hasil-uji.view`.

