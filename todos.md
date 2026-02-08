# Task Tracker - Inventory Dashboard Insights

## Goals

- Make Dashboard Inventori lebih insightful (stok health, top movers, disposal sampel always accessible)
- Seragamkan gaya tombol (primary solid + secondary outline)

## Implementation

- [x] Tambah widget Disposal Sampel yang selalu tampil + summary counts + batch terakhir
- [x] Tambah section "Barang Paling Boros (7 hari terakhir)" berdasarkan volume issue
- [x] Tambah section "Kesehatan Stok" dengan bullet graph (actual vs min marker)
- [x] Rapikan tombol header dashboard pakai `x-button` (outline + primary/danger/secondary)
- [x] Pindahkan Quick Actions ke bagian bawah (biar insight tampil dulu)

## Tests (TDD)

- [x] Tambah/ubah test dashboard untuk disposal widget always visible
- [x] Tambah/ubah test dashboard untuk top movers
- [x] Tambah/ubah test dashboard untuk kesehatan stok + bullet graph
- [x] Tambah test dashboard untuk summary counts disposal

## Verification

- [x] `php vendor/bin/pest tests/Feature/Inventory/DashboardTest.php`

## Release

- [ ] Commit perubahan (belum dilakukan)
- [ ] Push ke GitHub (belum dilakukan)
