# Generator Berita Acara Penerimaan

Sistem otomatis untuk menghasilkan dokumen **Berita Acara Penerimaan** dalam format HTML dan PDF.

## 🚀 Fitur

- ✅ Generate dokumen HTML dari template Jinja2
- ✅ Optional PDF output (membutuhkan WeasyPrint)
- ✅ Integrasi penuh dengan Laravel via Artisan Command
- ✅ Data diambil dari API Laravel secara otomatis
- ✅ Logo embedded sebagai data URI (self-contained document)
- ✅ Format sesuai standar Pusdokkes Polri

## 📋 Prerequisites

### 1. Python 3.9+
```bash
python --version
```

### 2. Install Jinja2
```bash
pip install jinja2
```

### 3. (Optional) Install WeasyPrint untuk PDF
**Windows:**
1. Download dan install GTK3 Runtime dari: https://github.com/tschoonj/GTK-for-Windows-Runtime-Environment-Installer/releases
2. Install WeasyPrint:
```bash
pip install weasyprint
```

**Linux/macOS:**
```bash
pip install weasyprint
```

## 📁 Struktur Folder

```
├── scripts/
│   └── generate_berita_acara.py    # Python generator script
├── templates/
│   └── berita_acara_penerimaan.html.j2  # Jinja2 template
├── output/
│   └── [Generated HTML/PDF files]
└── public/assets/
    ├── logo-tribrata-polri.png     # Logo Tribrata (REQUIRED)
    └── logo-pusdokkes-polri.png    # Logo Pusdokkes (REQUIRED)
```

## 🎨 Setup Logo

**PENTING:** Siapkan file logo dalam format PNG:

1. `public/assets/logo-tribrata-polri.png` - Logo Tribrata POLRI
2. `public/assets/logo-pusdokkes-polri.png` - Logo Pusdokkes POLRI

Ukuran rekomendasi: 300x300 px (transparan background)

## 🔧 Cara Penggunaan

### Metode 1: Via Artisan Command (Rekomendasi)

```bash
# Generate HTML only
php artisan berita-acara:generate REQ-2025-0001

# Generate HTML + PDF
php artisan berita-acara:generate REQ-2025-0001 --pdf
```

### Metode 2: Direct Python Script

```bash
python scripts/generate_berita_acara.py --id REQ-2025-0001

# Dengan PDF
python scripts/generate_berita_acara.py --id REQ-2025-0001 --pdf
```

### Metode 3: Otomatis di Controller

Tambahkan di `RequestController@store`:

```php
use Symfony\Component\Process\Process;

// Setelah TestRequest berhasil disimpan
$process = new Process([
    'python',
    base_path('scripts/generate_berita_acara.py'),
    '--id', $testRequest->request_number,
    '--api', 'http://127.0.0.1:8000/api/requests',
    '--outdir', base_path('output'),
    '--pdf'
]);
$process->setTimeout(60);
$process->start(); // Run in background
```

## 🌐 API Endpoint

Generator mengambil data dari:
```
GET http://127.0.0.1:8000/api/requests/{request_number}
```

Response format:
```json
{
  "request_no": "REQ-2025-0001",
  "surat_permintaan_no": "B/123/IV/2025",
  "received_date": "05 Oktober 2025",
  "customer_rank_name": "IPDA Budi Santoso",
  "customer_no": "12345678",
  "unit": "Polda Metro Jaya",
  "addressed_to": "Kapuslabfor Polri c.q Sub-Satker Farmapol",
  "tests_summary": "Identifikasi UV-VIS; Identifikasi GC-MS",
  "sample_count": 3,
  "samples": [
    {
      "name": "Tablet Putih",
      "tests": "Identifikasi UV-VIS; Identifikasi GC-MS",
      "active": "Trihexyphenidyl"
    }
  ],
  "submitted_by": "IPDA Budi Santoso",
  "received_by": "Petugas Administrasi & Petugas Laboratorium",
  "source_printed_at": "05 Oktober 2025 10:30:00"
}
```

## 📝 Output Files

File akan disimpan di folder `output/`:
- HTML: `Berita_Acara_Penerimaan_REQ-2025-0001.html`
- PDF: `Berita_Acara_Penerimaan_REQ-2025-0001.pdf`

## 🔍 Troubleshooting

### Error: "Python not found"
Pastikan Python sudah terinstall dan ada di system PATH.

### Error: "No module named 'jinja2'"
Install Jinja2:
```bash
pip install jinja2
```

### Error: "OSError: cannot load library 'gobject-2.0-0'"
Install GTK3 Runtime untuk Windows (lihat Prerequisites).

### Logo tidak muncul
Pastikan file logo ada di:
- `public/assets/logo-tribrata-polri.png`
- `public/assets/logo-pusdokkes-polri.png`

## 🎯 Tips Produksi

1. **Background Processing**: Gunakan Laravel Queue untuk generate dokumen di background
2. **File Storage**: Simpan output ke `storage/app/documents` dan serve via controller
3. **Caching**: Cache API response untuk mengurangi database queries
4. **Monitoring**: Log setiap dokumen yang di-generate untuk audit trail

## 📞 Support

Jika ada masalah, cek:
1. Python version: `python --version` (harus 3.9+)
2. Jinja2 installed: `pip list | grep -i jinja2`
3. API endpoint accessible: `curl http://127.0.0.1:8000/api/requests/REQ-2025-0001`
4. Logo files exist and readable

---

**Dibuat untuk Pusdokkes POLRI - Laboratorium Pengujian Mutu Farmasi Kepolisian**
