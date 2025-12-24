<!-- Markdown version of the report -->

<div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #D8DEE9;padding-bottom:10px;margin-bottom:12px;">
  <img src="/assets/img/tribrata.png" alt="Logo Tribrata" style="height:70px;">
  <div style="text-align:center;flex:1;">
    <h1 style="margin:0;font-size:20px;letter-spacing:.5px;text-transform:uppercase;">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</h1>
    <p style="margin:4px 0 0 0;font-size:14px;">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</p>
    <p style="margin:4px 0 0 0;color:#5b6779;font-size:12px;">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 • Telp/Fax: 021-4700921 • Email: labmutufarmapol@gmail.com</p>
  </div>
  <img src="/assets/img/pusdokkes.png" alt="Logo Pusdokkes Polri" style="height:70px;">
</div>

**LAPORAN HASIL UJI**  
**Nomor:** `{{ laporan.nomor ?? 'FLHU001' }}`  
**Halaman:** 1/1

## Informasi Pelanggan & Sampel

| Field | Nilai |
|---|---|
| Nama Pelanggan | {{ pelanggan.nama }} |
| Alamat Pelanggan | {{ pelanggan.alamat }} |
| Nama Sampel | {{ sampel.nama }} |
| Jumlah Sampel | {{ sampel.jumlah_diuji }} |
| No Batch | {{ sampel.no_batch }} |
| Exp. Date | {{ sampel.exp_date }} |
| Tanggal Penerimaan Sampel | {{ sampel.tanggal_penerimaan }} |
| Kode Sampel | {{ sampel.kode }} |

## Hasil Pengujian

| Parameter Uji | Hasil | Metode Uji |
|---|---|---|
| {{ hasil.parameter }} | {{ hasil.nilai }} | {{ hasil.metode }} |

Referensi: _{{ hasil.referensi }}_

> Hasil uji hanya berlaku untuk sampel yang diterima oleh laboratorium.

---

**Jakarta, {{ laporan.bulan_tahun }}**  
Pusat Kedokteran dan Kesehatan Polri  
Laboratorium Pengujian Mutu Farmasi Kepolisian

|  |  |  |
|---|---|---|
| **KAFARMAPOL**  
KUSWARDANI, S.Si., Apt., M.Farm  
KOMBES POL. NRP. 70040687 | **Paraf verifikator**  
1. Teknis:  
2. Mutu:  
3. Administrasi: |  |
