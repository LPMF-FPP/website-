# WhatsApp Notification Setup (Narawa)

Aplikasi ini menggunakan [Narawa](https://github.com/maulanadiooo/narawa) untuk mengirim notifikasi WhatsApp. Narawa adalah WhatsApp API berbasis Baileys yang berjalan di Bun runtime dengan Elysia framework.

## Prasyarat

1. **Bun Runtime** - Install Bun dari https://bun.sh
2. **Nomor WhatsApp** - Siapkan nomor WhatsApp untuk dijadikan bot/sender

## Instalasi Narawa

### 1. Clone Repository Narawa

```bash
git clone https://github.com/maulanadiooo/narawa.git
cd narawa
```

### 2. Install Dependencies

```bash
bun install
```

### 3. Konfigurasi Narawa

Sesuaikan konfigurasi di file konfigurasi Narawa (lihat dokumentasi Narawa untuk detail).

### 4. Jalankan Narawa Server

```bash
bun run start
# atau
bun run dev
```

Server Narawa akan berjalan di `http://localhost:3000` (atau port yang dikonfigurasi).

### 5. Scan QR Code

Saat pertama kali dijalankan, Narawa akan menampilkan QR code. Scan QR code tersebut dengan aplikasi WhatsApp di smartphone Anda untuk autentikasi.

## Konfigurasi Laravel

### 1. Update File `.env`

Tambahkan konfigurasi berikut ke file `.env` Anda:

```env
WHATSAPP_API_URL=http://localhost:3000
WHATSAPP_API_KEY=your-api-key-here
```

**Catatan:**
- `WHATSAPP_API_URL`: Base URL server Narawa Anda
- `WHATSAPP_API_KEY`: API key jika Narawa dikonfigurasi dengan autentikasi (opsional)

### 2. Run Seeder (Jika belum)

```bash
php artisan db:seed --class=SystemSettingSeeder
```

### 3. Konfigurasi di Settings Page

1. Buka halaman Settings (`/settings`)
2. Scroll ke section **Automation**
3. **Centang** checkbox **WhatsApp** di bagian Notifikasi
4. **Masukkan nomor WhatsApp tujuan** dengan format: `628123456789` (tanpa +, spasi, atau tanda hubung)
5. (Opsional) Sesuaikan template pesan WhatsApp di bagian Templates
6. Klik **Simpan**

## API Endpoint Narawa

Laravel akan memanggil endpoint berikut untuk mengirim pesan:

```
POST {WHATSAPP_API_URL}/send
Content-Type: application/json
Authorization: Bearer {WHATSAPP_API_KEY}

{
  "to": "628123456789",
  "message": "Your message here"
}
```

**Response yang diharapkan:**
```json
{
  "success": true,
  "messageId": "..."
}
```

## Testing

### Test Notifikasi WhatsApp

Untuk test notifikasi WhatsApp, issue nomor baru melalui sistem:

1. Pastikan checkbox **WhatsApp** sudah dicentang di Settings
2. Pastikan **nomor WhatsApp tujuan** sudah diisi
3. Issue nomor baru (misalnya LHU)
4. Check logs di `storage/logs/laravel.log` untuk melihat status pengiriman

### Troubleshooting

**Pesan tidak terkirim:**
1. Check apakah server Narawa berjalan: `curl http://localhost:3000`
2. Check logs Laravel: `tail -f storage/logs/laravel.log`
3. Pastikan nomor tujuan dalam format yang benar (628xxx)
4. Pastikan WhatsApp sudah ter-autentikasi di Narawa (scan QR)

**Error "WhatsApp service not configured":**
- Pastikan `WHATSAPP_API_URL` sudah diset di `.env`
- Jalankan `php artisan config:clear` untuk refresh config

**Error "WhatsApp recipient not configured":**
- Pastikan nomor tujuan sudah diisi di Settings page

## Production Deployment

Untuk production, disarankan:

1. **Deploy Narawa di server terpisah** (VPS/cloud)
2. **Gunakan Process Manager** seperti PM2 atau systemd
3. **Setup HTTPS** untuk Narawa dengan reverse proxy (nginx/caddy)
4. **Update `WHATSAPP_API_URL`** dengan URL production
5. **Setup API Key** untuk keamanan
6. **Monitor logs** Narawa dan Laravel

### Contoh dengan PM2

```bash
# Install PM2
npm install -g pm2

# Start Narawa with PM2
cd narawa
pm2 start "bun run start" --name narawa

# Save PM2 configuration
pm2 save
pm2 startup
```

## Template Pesan

Template pesan WhatsApp mendukung placeholder berikut:

- `{SCOPE}` - Tipe dokumen (LHU, BA, dll)
- `{NUMBER}` - Nomor yang di-issue
- `{REQ}` - Nomor request

Contoh template default:
```
*[LIMS]* Nomor {SCOPE} {NUMBER} terbit untuk {REQ}
```

Format Markdown WhatsApp didukung:
- `*bold*` untuk **bold**
- `_italic_` untuk _italic_
- `~strikethrough~` untuk ~~strikethrough~~
- ``` untuk monospace

## Referensi

- Narawa GitHub: https://github.com/maulanadiooo/narawa
- Baileys (WhatsApp Web API): https://github.com/WhiskeySockets/Baileys
- Bun Runtime: https://bun.sh
- Elysia Framework: https://elysiajs.com
