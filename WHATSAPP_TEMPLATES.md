# WhatsApp Notification Templates

## 📱 Overview

Template pesan WhatsApp untuk notifikasi otomatis kepada penyidik pada setiap milestone pengujian di LPMF LIMS.

## 🎯 Template Structure

Setiap template memiliki struktur sebagai berikut:

1. **Greetings**: Salam pembuka dengan nama penyidik
2. **Nama Penyidik**: Personalisasi pesan dengan nama
3. **Isi**: Informasi status dan detail milestone
4. **Penutup**: Salam hormat dari Tim LPMF
5. **Follow Up**: Informasi kontak untuk pertanyaan

## 📋 Available Milestones

### 1. REQUEST_RECEIVED
**Trigger**: Ketika permohonan pengujian diterima dan terdaftar di sistem

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Terima kasih telah mempercayakan pengujian kepada Laboratorium Pengujian Mutu Farmasi dan Pangan (LPMF).

📋 *Permohonan Pengujian Anda Telah Diterima*

Nomor Resi: *{resi}*
Status: Permohonan telah terdaftar dalam sistem kami

Selanjutnya tim kami akan memproses permohonan Anda dan kami akan memberikan update secara berkala melalui WhatsApp ini.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Jika ada pertanyaan, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

### 2. REVIEW_DONE_READY_FOR_TEST
**Trigger**: Ketika sampel telah dikaji ulang dan siap untuk pengujian

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Kami informasikan bahwa sampel pengujian Anda telah kami terima, dikaji ulang, dan siap untuk tahap pengujian.

📦 *Sampel Siap untuk Pengujian*

Nomor Resi: *{resi}*
Status: Review selesai, sampel siap diuji

Tim analis kami akan segera memulai proses pengujian sesuai dengan metode yang telah ditentukan. Anda akan menerima notifikasi untuk setiap tahap pengujian.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Jika ada pertanyaan, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

### 3. PREPARATION_DONE
**Trigger**: Ketika preparasi sampel selesai

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Kami informasikan bahwa tahap preparasi sampel untuk pengujian Anda telah selesai dilakukan.

🧪 *Preparasi Sampel Selesai*

Nomor Resi: *{resi}*
Status: Preparasi selesai, siap untuk instrumentasi

Sampel telah dipersiapkan dan siap untuk tahap instrumentasi. Tim analis akan melanjutkan ke tahap pengujian dengan instrumen.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Jika ada pertanyaan, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

### 4. INSTRUMENTATION_DONE
**Trigger**: Ketika instrumentasi (pengujian dengan alat) selesai

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Kami informasikan bahwa tahap instrumentasi (pengujian dengan alat) untuk sampel Anda telah selesai dilakukan.

🔬 *Instrumentasi Selesai*

Nomor Resi: *{resi}*
Status: Instrumentasi selesai, sedang interpretasi hasil

Data hasil instrumentasi telah diperoleh dan saat ini sedang dilakukan interpretasi oleh tim analis untuk memastikan akurasi hasil.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Jika ada pertanyaan, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

### 5. INTERPRETATION_DONE
**Trigger**: Ketika interpretasi hasil pengujian selesai

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Kabar baik! Interpretasi hasil pengujian untuk sampel Anda telah selesai dilakukan oleh tim analis kami.

✅ *Interpretasi Hasil Selesai*

Nomor Resi: *{resi}*
Status: Interpretasi selesai, sedang finalisasi laporan

Hasil pengujian telah diinterpretasi dan divalidasi. Saat ini tim kami sedang menyusun laporan hasil uji yang lengkap.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Jika ada pertanyaan, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

### 6. READY_FOR_PICKUP
**Trigger**: Ketika laporan hasil uji siap diambil

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Kami dengan senang hati menginformasikan bahwa Laporan Hasil Uji untuk sampel Anda telah selesai dan siap untuk diambil.

📄 *Laporan Hasil Uji Siap Diambil*

Nomor Resi: *{resi}*
Status: Laporan siap diambil

Silakan mengambil laporan hasil uji Anda di kantor LPMF pada jam kerja (Senin-Jumat, 08:00-16:00 WIB). Jangan lupa membawa tanda pengenal dan nomor resi ini.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Jika ada pertanyaan atau perlu koordinasi pengambilan, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

### 7. HANDOVER_COMPLETED
**Trigger**: Ketika laporan telah diserahkan dan diterima

**Template**:
```
Assalamu'alaikum {nama_penyidik},

Terima kasih! Laporan Hasil Uji untuk sampel Anda telah diserahkan dan diterima.

🎉 *Laporan Telah Diserahkan*

Nomor Resi: *{resi}*
Status: Selesai - Laporan telah diterima

Terima kasih telah mempercayakan pengujian Anda kepada LPMF. Kami berharap hasil pengujian ini bermanfaat untuk keperluan Anda.

Kami menantikan kerja sama selanjutnya dengan Anda.

Salam Hormat,
*Tim LPMF*
Laboratorium Pengujian Mutu Farmasi dan Pangan

---
💬 Untuk pengujian selanjutnya atau feedback layanan kami, silakan hubungi kami di nomor ini. Pesan Anda akan segera kami balas.
```

## 🔧 Placeholders

Template menggunakan placeholder yang akan diganti secara otomatis:

| Placeholder | Deskripsi | Contoh |
|------------|-----------|--------|
| `{nama_penyidik}` | Nama penyidik/investigator | "Bapak Ahmad Yani" |
| `{resi}` | Nomor resi pengujian | "LPMF-2026-0001" |

## 📊 Template Statistics

| Milestone | Panjang (chars) | Emoji |
|-----------|----------------|-------|
| REQUEST_RECEIVED | ~562 | 📋 |
| REVIEW_DONE_READY_FOR_TEST | ~580 | 📦 |
| PREPARATION_DONE | ~539 | 🧪 |
| INSTRUMENTATION_DONE | ~572 | 🔬 |
| INTERPRETATION_DONE | ~541 | ✅ |
| READY_FOR_PICKUP | ~570 | 📄 |
| HANDOVER_COMPLETED | ~553 | 🎉 |

## 🎨 Design Principles

1. **Professional yet Friendly**: Menggunakan salam Islami dan bahasa formal tapi ramah
2. **Clear Status Updates**: Setiap pesan memberikan informasi status yang jelas
3. **Actionable Information**: Memberikan informasi yang dapat ditindaklanjuti
4. **Consistent Format**: Semua template mengikuti struktur yang sama
5. **Contact Support**: Setiap pesan menyediakan cara untuk menghubungi tim support

## 🔄 Workflow Mapping

```
TestRequest Created
    ↓
REQUEST_RECEIVED ← "Permohonan diterima"
    ↓
Sample Status: ADMIN_DONE
    ↓
REVIEW_DONE_READY_FOR_TEST ← "Siap diuji"
    ↓
Sample Status: PREPARATION_DONE
    ↓
PREPARATION_DONE ← "Preparasi selesai"
    ↓
Sample Status: INSTRUMENTATION_DONE
    ↓
INSTRUMENTATION_DONE ← "Instrumentasi selesai"
    ↓
Sample Status: INTERPRETATION_DONE
    ↓
INTERPRETATION_DONE ← "Interpretasi selesai"
    ↓
Sample Status: READY_FOR_DELIVERY
    ↓
READY_FOR_PICKUP ← "Siap diambil"
    ↓
TestRequest: handover_date set
    ↓
HANDOVER_COMPLETED ← "Serah terima selesai"
```

## 💾 Database Storage

Templates disimpan di `system_settings` table:

```sql
-- Templates
key: 'notifications.whatsapp.templates'
value: {json object dengan semua templates}

-- Enabled milestones
key: 'notifications.whatsapp.enabled_milestones'
value: ["REQUEST_RECEIVED", "REVIEW_DONE_READY_FOR_TEST", ...]
```

## 🚀 Usage in Code

### Observer Pattern
```php
// TestRequestObserver
$message = $notificationService->getMilestoneMessage('REQUEST_RECEIVED', [
    'nama_penyidik' => $testRequest->investigator->name,
    'resi' => $testRequest->receipt_number,
]);

// SampleObserver
$message = $notificationService->getMilestoneMessage('PREPARATION_DONE', [
    'nama_penyidik' => $sample->testRequest->investigator->name,
    'resi' => $sample->testRequest->receipt_number,
]);
```

### Manual Sending
```php
use App\Services\WhatsApp\NotificationService;

$service = app(NotificationService::class);
$message = $service->getMilestoneMessage('READY_FOR_PICKUP', [
    'nama_penyidik' => 'Bapak Ahmad',
    'resi' => 'LPMF-2026-0001',
]);
```

## 📝 Customization

Templates dapat dikustomisasi melalui:

1. **Settings Page**: `/settings` → Section "Notifikasi & Security"
2. **Database**: Direct update ke `system_settings` table
3. **Code**: Update `NotificationService::MILESTONE_TEMPLATES`

## ⚙️ Configuration

```php
// Enable/disable WhatsApp notifications
'notifications.whatsapp.enabled' => true

// GOWA service URL
'notifications.whatsapp.base_url' => 'http://localhost:3000'

// Basic auth
'notifications.whatsapp.basic_user' => 'lpmf'
'notifications.whatsapp.basic_pass' => '[encrypted]'
```

## 🧪 Testing

Untuk test template:
1. Gunakan page `/settings`
2. Scroll ke "Test WhatsApp"
3. Masukkan nomor telepon
4. Klik "Test"

Pesan akan menggunakan milestone default dengan data sample.

---

**Last Updated**: 11 Januari 2026  
**Version**: v1.3.0
