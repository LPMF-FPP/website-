# Quick Start: Safe Mode v2

## 🚀 TL;DR

Safe Mode v2 sudah diimplementasikan! Sistem theme sekarang **hanya mengubah visual** (warna, shadow, border) **tanpa menyentuh layout**.

## ✅ What's Changed

| File | Status | Keterangan |
|------|--------|------------|
| `styles/pd.ultrasafe.tokens.css` | ✨ **Baru** | CSS variables untuk light/dark theme |
| `styles/pd.framework-bridge.css` | ✨ **Baru** | Visual-only styling untuk Tailwind |
| `scripts/theme-toggle-v2.js` | ✨ **Baru** | Minimalist theme toggle (hanya set attribute) |
| `resources/views/layouts/app.blade.php` | 🔄 **Updated** | Import order diperbaiki |
| `scripts/check-overlay.mjs` | 🔄 **Updated** | Check file-file Safe Mode v2 |

## 📋 Test Checklist

1. **Start server:**
   ```bash
   php artisan serve
   ```

2. **Buka browser:**
   - Go to `http://localhost:8000`
   - Login ke dashboard

3. **Toggle tema:**
   - Klik button toggle tema (moon/sun icon)
   - Toggle beberapa kali (light ↔ dark)

4. **Verify:**
   - [ ] Tidak ada elemen yang "melompat" atau bergeser
   - [ ] Hanya warna yang berubah
   - [ ] Cards, buttons, inputs tetap di posisi yang sama
   - [ ] Scroll position tidak berubah
   - [ ] Shadow dan border-radius berubah (lebih halus)

## 🔍 Inspect Mode (Optional)

Untuk verify bahwa layout tidak berubah:

1. Buka DevTools (F12)
2. Klik **Inspector/Elements**
3. Select elemen (misalnya card atau button)
4. Toggle tema beberapa kali
5. Di tab **Styles**, perhatikan:
   - ✅ `background-color`, `color`, `border-color` berubah
   - ✅ `box-shadow`, `border-radius` berubah
   - ❌ `margin`, `padding`, `width`, `height` **TIDAK** berubah

## 🛡️ Build Guard

Sebelum commit atau deploy, validate overlay files:

```bash
node scripts/check-overlay.mjs
```

**Expected output:**
```
Checking safe overlay files...

Checking: styles/pd.ultrasafe.tokens.css
✅ Safe overlay check passed for styles/pd.ultrasafe.tokens.css
   No layout-affecting properties found.

Checking: styles/pd.framework-bridge.css
✅ Safe overlay check passed for styles/pd.framework-bridge.css
   No layout-affecting properties found.

✅ Safe overlay validation complete! 🎉
```

## 🐛 Troubleshooting

### Tema tidak berubah?
- Clear browser cache (Ctrl+Shift+R)
- Check Console untuk error JavaScript
- Pastikan `theme-toggle-v2.js` dimuat

### Masih ada yang bergeser?
1. Identify elemen yang bermasalah
2. Inspect di DevTools
3. Check properti layout mana yang berubah
4. Report issue dengan screenshot + nama class/ID elemen

### Warna tidak sesuai?
- Check urutan CSS import di `app.blade.php`
- Pastikan Tailwind CSS dimuat sebelum pd.ultrasafe.tokens.css
- Clear Laravel cache: `php artisan cache:clear`

## 📚 Dokumentasi Lengkap

Lihat **SAFE-MODE-V2.md** untuk:
- Penjelasan detail prinsip Safe Mode v2
- Cara menambahkan custom styling
- Migration guide dari versi lama
- Troubleshooting advanced

## 🎨 Theme API

JavaScript API untuk kontrol tema:

```javascript
// Set tema
window.pdTheme.set('dark');   // or 'light'

// Toggle tema
window.pdTheme.toggle();

// Get tema saat ini
const current = window.pdTheme.get();  // 'dark' or 'light'

// Reset ke system preference
window.pdTheme.system();
```

## 📝 File Lama (Backup)

File lama sudah di-backup:
- `scripts/theme-toggle.js.backup` ← Backup dari theme-toggle.js

File yang **TIDAK dipakai lagi** (tapi masih ada di folder):
- `styles/base.css` ← **JANGAN import lagi** (terlalu agresif)
- `styles/components.css` ← **JANGAN import lagi** (ubah layout)
- `styles/tokens.css` ← Diganti dengan `pd.ultrasafe.tokens.css`

---

**✅ Safe Mode v2 Active** - Visual Only, No Layout Impact!
