# Pusdokkes Design System - Safe Mode v2

## 📋 Overview

**Safe Mode v2** adalah pendekatan non-invasif untuk implementasi theme system yang **hanya mengubah visual** tanpa menyentuh layout sama sekali. Sistem ini dirancang untuk mencegah masalah "tampilan bergeser" yang sering terjadi dengan overlay CSS yang agresif.

## 🎯 Prinsip Utama

### ✅ Yang Diperbolehkan (Visual Only)
- **Warna**: `color`, `background-color`, `border-color`
- **Shadow**: `box-shadow`, `text-shadow`
- **Border**: `border-radius`, `border-width`, `border-style`
- **Opacity**: `opacity`, `filter`
- **Outline**: `outline`, `outline-offset`
- **Typography**: `font-*`, `text-*`, `letter-spacing`
- **Transitions**: `transition`, `animation`

### ❌ Yang Dilarang (Layout Affecting)
- **Display**: `display`, `visibility`
- **Position**: `position`, `top`, `right`, `bottom`, `left`, `inset`
- **Flexbox**: `flex*`, `justify-content`, `align-items`
- **Grid**: `grid*`
- **Box Model**: `width`, `height`, `margin`, `padding`, `gap`
- **Transform**: `transform` (kecuali untuk efek visual ringan)
- **Overflow**: `overflow`, `overflow-x`, `overflow-y`

## 📁 File Structure

```
styles/
├── pd.ultrasafe.tokens.css      ← CSS Variables only (light/dark themes)
└── pd.framework-bridge.css      ← Visual-only bridge untuk Tailwind/Bootstrap

scripts/
├── theme-toggle-v2.js           ← Non-invasive theme toggle
├── theme-toggle.js.backup       ← Backup dari versi lama
└── check-overlay.mjs            ← Build guard untuk validasi

resources/views/layouts/
└── app.blade.php                ← Updated dengan Safe Mode v2
```

## 🔧 Implementasi

### 1. CSS Tokens (`pd.ultrasafe.tokens.css`)

File ini **hanya berisi CSS variables**. Tidak ada selector ke elemen HTML, sehingga **zero impact** ke layout.

```css
html[data-pd-safe] {
  --pd-bg: #ffffff;
  --pd-surface: #f7f8fa;
  --pd-text: #1b2128;
  --pd-primary: #1e40af;
  /* ... dst */
}

html[data-pd-safe][data-theme="dark"] {
  --pd-bg: #0b0c0f;
  --pd-surface: #111318;
  --pd-text: #edf0f3;
  /* ... dst */
}
```

### 2. Framework Bridge (`pd.framework-bridge.css`)

File ini **hanya mengubah properti visual** dari elemen yang sudah ada. Tidak ada perubahan layout.

**Contoh safe styling:**
```css
html[data-pd-safe] .card {
  background-color: var(--pd-surface);
  border-color: var(--pd-border);
  border-radius: var(--pd-radius-lg);
  box-shadow: var(--pd-shadow-sm);
  /* ✅ Hanya visual, tidak ubah layout */
}
```

**Yang TIDAK dilakukan:**
```css
/* ❌ JANGAN seperti ini */
html[data-pd-safe] .card {
  display: flex;           /* Layout! */
  padding: 20px;           /* Layout! */
  margin: 10px;            /* Layout! */
  width: 100%;             /* Layout! */
}
```

### 3. Theme Toggle (`theme-toggle-v2.js`)

JavaScript yang minimalis, **hanya set attribute**:

```javascript
// Hanya set attribute, tidak manipulasi DOM
document.documentElement.setAttribute('data-pd-safe', '');
document.documentElement.setAttribute('data-theme', 'dark');

// API public
window.pdTheme = {
  set: function(theme) { /* ... */ },
  toggle: function() { /* ... */ },
  get: function() { /* ... */ }
};
```

### 4. Import Order di `app.blade.php`

**Urutan yang benar:**

```html
<!-- 1. Framework CSS (Tailwind) -->
@vite(['resources/css/app.css', 'resources/js/app.js'])

<!-- 2. Design System Tokens -->
<link rel="stylesheet" href="{{ asset('styles/pd.ultrasafe.tokens.css') }}">

<!-- 3. Framework Bridge -->
<link rel="stylesheet" href="{{ asset('styles/pd.framework-bridge.css') }}">

<!-- 4. Theme Toggle Script -->
<script src="{{ asset('scripts/theme-toggle-v2.js') }}" defer></script>
```

**Kenapa urutan ini?**
- Tailwind dulu = base framework
- Tokens = variabel tema
- Bridge = styling visual yang override Tailwind dengan variabel tema
- Script = enable theme switching

## 🔒 Build Guard

Untuk mencegah kesalahan di masa depan, gunakan `check-overlay.mjs`:

```bash
# Check file-file Safe Mode v2 (default)
node scripts/check-overlay.mjs

# Check file spesifik
node scripts/check-overlay.mjs styles/pd.ultrasafe.tokens.css

# Check multiple files
node scripts/check-overlay.mjs styles/*.css
```

Script ini akan **memblokir build** jika menemukan properti layout di file overlay.

## ✅ Cara Test

### 1. Visual Check
```bash
# Start Laravel development server
php artisan serve

# Buka browser ke http://localhost:8000
# Toggle tema dengan button yang ada
# Pastikan tidak ada elemen yang "bergeser"
```

### 2. DevTools Inspection
1. Buka DevTools (F12)
2. Inspect elemen yang dulunya bermasalah
3. Di tab **Styles**, cek bahwa:
   - ✅ Hanya properti visual yang berubah saat toggle tema
   - ✅ Tidak ada `margin`, `padding`, `display`, dll yang berubah
   - ✅ Property layout masih dari Tailwind/framework asli

### 3. Layout Stability
1. Buka halaman dashboard
2. Toggle tema beberapa kali (light ↔ dark)
3. Pastikan:
   - ✅ Tidak ada "jumping" atau pergeseran elemen
   - ✅ Scroll position tidak berubah
   - ✅ Ukuran cards, buttons, inputs tetap sama
   - ✅ Hanya warna, shadow, border yang berubah

## 🐛 Troubleshooting

### Masalah: Tema tidak berubah
**Solusi:**
- Pastikan `<html>` memiliki attribute `data-pd-safe`
- Check di DevTools Console untuk error JavaScript
- Pastikan `theme-toggle-v2.js` dimuat dengan benar

### Masalah: Masih ada elemen yang bergeser
**Solusi:**
1. Identify elemen yang bermasalah
2. Check di DevTools → Styles → Computed
3. Lihat properti layout mana yang berubah
4. Cari di `pd.framework-bridge.css` dan hapus properti tersebut
5. Run `node scripts/check-overlay.mjs` untuk validate

### Masalah: Warna tidak apply
**Solusi:**
- Pastikan urutan import benar (Tailwind → Tokens → Bridge)
- Check CSS specificity - mungkin ada `!important` dari file lain
- Pastikan selector `html[data-pd-safe]` ada di HTML

## 📊 Comparison: Safe Mode v1 vs v2

| Aspect | v1 (Agresif) | v2 (Safe) |
|--------|-------------|-----------|
| Layout Properties | ❌ Ya | ✅ Tidak |
| Visual Only | ❌ Tidak | ✅ Ya |
| Reset CSS | ❌ Ya (`* { margin: 0; }`) | ✅ Tidak |
| Scope Selector | ❌ Global | ✅ `html[data-pd-safe]` |
| Layout Shifting | ❌ Sering | ✅ Tidak pernah |
| Framework Integration | ❌ Override | ✅ Complement |

## 🔄 Migration dari v1

Jika Anda menggunakan versi lama:

1. **Backup** file lama:
   ```bash
   copy styles\base.css styles\base.css.backup
   copy styles\components.css styles\components.css.backup
   copy scripts\theme-toggle.js scripts\theme-toggle.js.backup
   ```

2. **Update** `app.blade.php` sesuai contoh di atas

3. **Test** dengan visual check

4. **Hapus** file lama jika sudah yakin Safe Mode v2 bekerja dengan baik

## 📝 Custom Components

Jika ada komponen spesifik yang perlu styling tambahan:

```css
/* Tambahkan di pd.framework-bridge.css */
html[data-pd-safe] .komponen-custom {
  /* ✅ Visual only */
  background-color: var(--pd-surface);
  border-color: var(--pd-border);
  border-radius: var(--pd-radius-md);
  box-shadow: var(--pd-shadow-sm);
  
  /* ❌ JANGAN ubah layout */
  /* display: flex; */      ← HAPUS
  /* padding: 20px; */       ← HAPUS
  /* margin: 10px; */        ← HAPUS
}
```

## 🚀 Best Practices

1. **Selalu gunakan scope selector** `html[data-pd-safe]`
2. **Hanya ubah visual properties** (warna, shadow, border-radius)
3. **Test setiap perubahan** dengan toggle tema
4. **Run build guard** sebelum commit:
   ```bash
   node scripts/check-overlay.mjs
   ```
5. **Jangan pernah** override layout properties dari framework

## 📞 Support

Jika masih ada masalah:

1. Check file-file di `styles/` tidak ada yang miss-import
2. Run `node scripts/check-overlay.mjs` untuk validate
3. Buka DevTools dan inspect elemen yang bermasalah
4. Tambahkan comment di file yang bermasalah untuk di-review

---

**Safe Mode v2** = **Visual Only, No Layout Impact** ✨
