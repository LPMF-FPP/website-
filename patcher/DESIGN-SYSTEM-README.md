# Pusdokkes Design System

Sistem desain komprehensif dengan **safe overlay mode** yang tidak mengganggu layout eksisting, namun memberikan konsistensi visual yang menyeluruh.

## 📁 Struktur File

```
styles/
├── pd.ultrasafe.tokens.css      # Design tokens lengkap (50-900 color scale, typography, spacing, dll)
├── pd.components.css            # Komponen UI lengkap (navigation, buttons, forms, cards, tables, dll)
├── pd.framework-bridge.css      # Bridge untuk Tailwind CSS, Bootstrap, Laravel Blade
├── pd-safe-layers.css           # Legacy file (gunakan yang di atas)
scripts/
├── theme-toggle-safe.js         # Theme toggle yang aman
├── check-overlay.mjs           # Build guard untuk mencegah layout properties
test-safe-overlay.html          # Testing page untuk memverifikasi layout tidak rusak
design-system-demo.html         # Demo komprehensif semua tokens & komponen
```

## 🚀 Quick Start

### 1. Setup di Laravel Blade

```html
<!-- resources/views/layouts/app.blade.php -->
<html lang="id" data-pd-safe>
<head>
    <!-- Existing styles (Tailwind, Bootstrap, dll) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Pusdokkes Design System - Load AFTER existing styles -->
    <link rel="stylesheet" href="{{ asset('styles/pd.ultrasafe.tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('styles/pd.components.css') }}">
    <link rel="stylesheet" href="{{ asset('styles/pd.framework-bridge.css') }}">
</head>
<body>
    <!-- Your existing content -->
    
    <!-- Theme toggle script -->
    <script src="{{ asset('scripts/theme-toggle-safe.js') }}" defer></script>
</body>
</html>
```

### 2. Aktivasi Safe Mode

Tambahkan `data-pd-safe` pada elemen `<html>`:

```html
<html lang="id" data-pd-safe>
```

**Semua styling hanya akan aktif dengan attribute ini!**

### 3. Theme Toggle

```html
<button onclick="toggleTheme()">🌙 Toggle Theme</button>
```

JavaScript akan otomatis mengatur `data-theme="dark"` untuk dark mode.

## 🎨 Design Tokens

### Color Palettes (50-900 Scale)

```css
/* Primary Blue */
--pd-blue-50: #eff6ff;    /* Light background */
--pd-blue-100: #dbeafe;   
--pd-blue-200: #bfdbfe;   
--pd-blue-300: #93c5fd;   
--pd-blue-400: #60a5fa;   
--pd-blue-500: #3b82f6;   
--pd-blue-600: #2563eb;   /* Primary color */
--pd-blue-700: #1d4ed8;   
--pd-blue-800: #1e40af;   
--pd-blue-900: #1e3a8a;   /* Dark text */

/* Status Colors (Green, Orange, Red, Cyan) */
--pd-success: var(--pd-green-600);
--pd-warning: var(--pd-orange-500);
--pd-error: var(--pd-red-600);
--pd-info: var(--pd-cyan-600);
```

### Semantic Colors

```css
/* Light Theme */
--pd-background: var(--pd-gray-50);
--pd-surface: #ffffff;
--pd-surface-variant: var(--pd-gray-100);
--pd-text-primary: var(--pd-gray-900);
--pd-text-secondary: var(--pd-gray-600);
--pd-text-muted: var(--pd-gray-500);
--pd-border: var(--pd-gray-200);
```

### Typography Scale

```css
/* Font Sizes */
--pd-text-xs: 0.75rem;     /* 12px */
--pd-text-sm: 0.875rem;    /* 14px */
--pd-text-base: 1rem;      /* 16px */
--pd-text-lg: 1.125rem;    /* 18px */
--pd-text-xl: 1.25rem;     /* 20px */
--pd-text-2xl: 1.5rem;     /* 24px */
--pd-text-3xl: 1.875rem;   /* 30px */
--pd-text-4xl: 2.25rem;    /* 36px */

/* Typography Hierarchy */
--pd-h1-size: var(--pd-text-4xl);
--pd-h1-weight: var(--pd-font-bold);
--pd-h2-size: var(--pd-text-3xl);
--pd-h2-weight: var(--pd-font-semibold);
--pd-body-size: var(--pd-text-base);
--pd-body-leading: var(--pd-leading-relaxed);
```

### Spacing Scale (8px base)

```css
--pd-space-1: 0.25rem;     /* 4px */
--pd-space-2: 0.5rem;      /* 8px */
--pd-space-3: 0.75rem;     /* 12px */
--pd-space-4: 1rem;        /* 16px */
--pd-space-6: 1.5rem;      /* 24px */
--pd-space-8: 2rem;        /* 32px */
--pd-space-12: 3rem;       /* 48px */
--pd-space-16: 4rem;       /* 64px */
```

### Border Radius & Shadows

```css
/* Radius */
--pd-radius-xs: 0.125rem;   /* 2px */
--pd-radius-sm: 0.25rem;    /* 4px */
--pd-radius-md: 0.5rem;     /* 8px */
--pd-radius-lg: 0.75rem;    /* 12px */
--pd-radius-xl: 1rem;       /* 16px */
--pd-radius-full: 9999px;   /* pill shape */

/* Shadows/Elevation */
--pd-elevation-1: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
--pd-elevation-2: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--pd-elevation-3: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
--pd-elevation-4: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
--pd-elevation-5: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
```

### Timing & Easing

```css
/* Durations */
--pd-duration-fast: 150ms;
--pd-duration-base: 200ms;
--pd-duration-slow: 300ms;

/* Easing */
--pd-ease-out: cubic-bezier(0, 0, 0.2, 1);
--pd-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
--pd-ease-smooth: cubic-bezier(0.25, 0.46, 0.45, 0.94);

/* Component transitions */
--pd-transition-button: var(--pd-duration-fast) var(--pd-ease-out);
--pd-transition-input: var(--pd-duration-base) var(--pd-ease-out);
```

## 🧩 Komponen

### Navigation

```html
<!-- Header Sticky -->
<nav class="pd-header">
  <div class="navbar-content">
    <div class="brand">Pusdokkes</div>
    <div class="nav-links">
      <a href="#" class="pd-nav-link">Dashboard</a>
      <a href="#" class="pd-nav-link active">Sampel</a>
      <a href="#" class="pd-nav-link">Laporan</a>
    </div>
  </div>
</nav>

<!-- Breadcrumb -->
<nav class="pd-breadcrumb">
  <a href="#" class="pd-breadcrumb-link">Dashboard</a> /
  <a href="#" class="pd-breadcrumb-link">Sampel</a> /
  <span class="pd-breadcrumb-current">Detail</span>
</nav>

<!-- Tabs -->
<div class="pd-tabs">
  <button class="pd-tab active">Informasi</button>
  <button class="pd-tab">Hasil Uji</button>
  <button class="pd-tab">Dokumen</button>
</div>
```

### Buttons

```html
<!-- Varian -->
<button class="pd-btn-primary">Primary</button>
<button class="pd-btn-secondary">Secondary</button>
<button class="pd-btn-tertiary">Tertiary</button>
<button class="pd-btn-ghost">Ghost</button>

<!-- Ukuran -->
<button class="pd-btn pd-btn-sm">Small</button>
<button class="pd-btn pd-btn-md">Medium</button>
<button class="pd-btn pd-btn-lg">Large</button>

<!-- States -->
<button class="pd-btn" disabled>Disabled</button>
```

### Forms

```html
<form>
  <!-- Input Text -->
  <div class="form-group">
    <label class="pd-label">Nama Lengkap <span class="pd-label-required">*</span></label>
    <input type="text" class="pd-input" placeholder="Masukkan nama">
    <div class="pd-helper-text">Nama sesuai identitas resmi</div>
  </div>

  <!-- Select -->
  <div class="form-group">
    <label class="pd-label">Peran</label>
    <select class="pd-select">
      <option>Investigator</option>
      <option>Teknisi Lab</option>
    </select>
  </div>

  <!-- Textarea -->
  <div class="form-group">
    <label class="pd-label">Catatan</label>
    <textarea class="pd-textarea" placeholder="Tambahkan catatan..."></textarea>
  </div>

  <!-- Checkbox -->
  <label class="checkbox-label">
    <input type="checkbox" class="pd-checkbox">
    <span>Saya setuju dengan syarat dan ketentuan</span>
  </label>

  <!-- Radio -->
  <div class="radio-group">
    <label><input type="radio" name="gender" class="pd-radio"> Laki-laki</label>
    <label><input type="radio" name="gender" class="pd-radio"> Perempuan</label>
  </div>
</form>
```

### Cards & Containers

```html
<!-- Basic Card -->
<div class="pd-card">
  <div class="pd-card-header">
    <h4>Sampel #12345</h4>
  </div>
  <div class="card-body">
    <p>Konten kartu...</p>
  </div>
  <div class="pd-card-footer">
    <button class="pd-btn-sm">Aksi</button>
  </div>
</div>

<!-- Panel -->
<div class="pd-panel">
  <h4>Statistik</h4>
  <p>Data statistik...</p>
</div>
```

### Tables

```html
<table class="pd-table">
  <thead>
    <tr>
      <th>ID Sampel</th>
      <th>Jenis</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>#12345</td>
      <td>Darah</td>
      <td><span class="pd-badge-success">Selesai</span></td>
    </tr>
  </tbody>
</table>

<!-- Zebra Striping -->
<table class="pd-table pd-table-striped">
  <!-- ... -->
</table>
```

### Feedback Components

```html
<!-- Alerts -->
<div class="pd-alert pd-alert-success">✅ Berhasil disimpan</div>
<div class="pd-alert pd-alert-warning">⚠️ Peringatan</div>
<div class="pd-alert pd-alert-error">❌ Terjadi kesalahan</div>
<div class="pd-alert pd-alert-info">💡 Informasi</div>

<!-- Badges -->
<span class="pd-badge pd-badge-primary">Primary</span>
<span class="pd-badge pd-badge-success">Aktif</span>
<span class="pd-badge pd-badge-warning">Pending</span>
<span class="pd-badge pd-badge-error">Error</span>

<!-- Loading -->
<div class="pd-spinner"></div>

<!-- Toast -->
<div class="pd-toast">
  <p>Notifikasi toast</p>
</div>
```

### Empty States

```html
<div class="pd-empty-state">
  <div class="empty-icon">📭</div>
  <h4>Belum ada data</h4>
  <p>Mulai dengan menambahkan data pertama.</p>
  <button class="pd-btn">Tambah Data</button>
</div>
```

## 🔗 Framework Integration

### Tailwind CSS Bridge

Sistem otomatis memetakan class Tailwind ke design tokens:

```html
<!-- Otomatis menggunakan design tokens -->
<div class="bg-white shadow-lg rounded-lg">  <!-- → pd-surface, pd-elevation-4, pd-radius-lg -->
<button class="bg-blue-600 text-white">     <!-- → pd-primary, pd-on-primary -->
<input class="border-gray-300 rounded">     <!-- → pd-border, pd-radius-base -->
```

### Bootstrap Bridge

```html
<!-- Bootstrap classes otomatis di-theme -->
<button class="btn btn-primary">Primary</button>
<div class="card">Card Content</div>
<div class="alert alert-success">Success Message</div>
<form>
  <input class="form-control" type="text">
</form>
```

### Laravel Blade Components

```html
<!-- Laravel form components otomatis styled -->
<x-input-label>Label</x-input-label>
<x-text-input />
<x-primary-button>Submit</x-primary-button>
```

## 🛡️ Safety & Layout Protection

### CSS Layers Order

```css
@layer pd.tokens, pd.base, pd.components, pd.a11y, pd.utilities;
```

### Safe Properties Only

✅ **Allowed (Visual only):**
- `color`, `background-color`, `border-color`
- `border-radius`, `box-shadow`, `opacity`
- `outline`, `filter`, `transition`, `animation`
- `font-*`, `text-*`, `letter-spacing`

❌ **Forbidden (Layout affecting):**
- `display`, `position`, `width`, `height`
- `margin`, `padding`, `flex`, `grid`
- `transform`, `overflow`, `float`

### Build Guard

```bash
# Validasi CSS sebelum deployment
node scripts/check-overlay.mjs styles/pd.components.css
```

### Testing Layout Safety

```bash
# Buka test file untuk verifikasi
open test-safe-overlay.html
```

## 🌙 Dark Theme

Dark theme otomatis aktif dengan `data-theme="dark"`:

```javascript
// Toggle theme
function toggleTheme() {
  const html = document.documentElement;
  if (html.getAttribute('data-theme') === 'dark') {
    html.removeAttribute('data-theme');
  } else {
    html.setAttribute('data-theme', 'dark');
  }
}
```

Semua color tokens otomatis beradaptasi untuk dark mode.

## 📱 Responsive & Accessibility

### Breakpoints

```css
--pd-screen-sm: 640px;   /* Mobile */
--pd-screen-md: 768px;   /* Tablet */
--pd-screen-lg: 1024px;  /* Desktop */
--pd-screen-xl: 1280px;  /* Large Desktop */
```

### Accessibility Features

- **Focus management**: Consistent focus rings
- **Reduced motion**: Respects `prefers-reduced-motion`
- **High contrast**: Enhanced borders for `prefers-contrast: high`
- **Color contrast**: WCAG AA compliant
- **Skip links**: Built-in navigation shortcuts

## 🚀 Performance

- **CSS Layers**: Efficient cascade management
- **CSS Variables**: Dynamic theming without runtime overhead
- **Minimal Specificity**: Avoids CSS conflicts
- **Tree Shaking**: Only load components you use

## 📊 Data Visualization

```css
/* Chart color palette */
--pd-data-1: var(--pd-blue-500);
--pd-data-2: var(--pd-green-500);
--pd-data-3: var(--pd-orange-500);
--pd-data-4: var(--pd-red-500);
--pd-data-5: var(--pd-cyan-500);
--pd-data-6: var(--pd-indigo-500);
```

```html
<div class="pd-chart">
  <div class="pd-chart-legend">Legend</div>
  <!-- Chart content -->
</div>
```

## 🔧 Troubleshooting

### Layout Issues

1. **Pastikan `data-pd-safe` ada** pada `<html>` element
2. **Load order**: Pusdokkes CSS harus AFTER existing frameworks
3. **Check console**: Jalankan build guard untuk validasi

### Theme Toggle Tidak Bekerja

1. **Script loaded**: Pastikan `theme-toggle-safe.js` di-include
2. **Function available**: Check `toggleTheme()` di browser console
3. **Attribute**: Periksa `data-theme="dark"` di HTML element

### Styling Tidak Muncul

1. **Activation**: Pastikan `data-pd-safe` attribute aktif
2. **CSS Load**: Check Network tab di DevTools
3. **Specificity**: Pusdokkes menggunakan layers, pastikan no conflicts

## 📝 Changelog

### v2.0 - Safe Overlay Mode
- ✅ Complete token system (color 50-900, typography, spacing)
- ✅ Comprehensive component library
- ✅ Framework bridge (Tailwind, Bootstrap, Laravel)
- ✅ Safe mode with layout protection
- ✅ Build guard for CSS validation
- ✅ Dark theme support
- ✅ Accessibility enhancements

### Contributing

1. Gunakan **safe properties only** (no layout impact)
2. Test dengan `test-safe-overlay.html`
3. Validate dengan `check-overlay.mjs`
4. Follow design token conventions
5. Update documentation

---

**🎉 Happy designing with Pusdokkes Design System!**

## 🧩 Semantic Utility Classes (New)

Untuk konsistensi tematik dan mengurangi ketergantungan pada kelas Tailwind warna mentah (`bg-gray-100`, `text-gray-700`, dll), sistem menyediakan layer utilitas semantik. Gunakan ini untuk elemen UI baru agar otomatis beradaptasi dengan dark mode dan kemungkinan future themes.

| Kategori | Kelas | Peran |
|----------|-------|------|
| Surface | `surface-sem` | Permukaan utama kartu / panel |
| Surface Alt | `surface-sem-alt` | Permukaan alternatif / blok internal |
| Surface Muted | `surface-sem-mute` | Permukaan tereduksi / badge pasif |
| Card | `card-sem` | Wrapper kartu dengan padding & elevasi ringan |
| Border | `border-sem` | Border standar kontras medium |
| Border Subtle | `border-sem-subtle` | Border lembut (divider / elemen sekunder) |
| Text High | `text-sem-high` | Teks utama (judul, nilai penting) |
| Text Mid | `text-sem-mid` | Teks isi / paragraf utama |
| Text Dim | `text-sem-dim` | Teks sekunder / metadata |
| Text Muted | `text-sem-muted` | Teks sangat redup / placeholder |
| Line | `bg-sem-line` | Garis progres / pembatas |
| Mute (bg) | `bg-sem-mute` | Latar badge / status pending |
| Muted Hover | `hover:bg-sem-muted` | Hover state netral |

### Prinsip Penggunaan
1. Pilih kelas berbasis PERAN, bukan warna spesifik.
2. Jangan mencampur kelas semantik dengan hard-coded palette pada elemen yang sama (hindari `bg-gray-100 surface-sem`).
3. Jika butuh varian status (success / warning / error), kombinasikan dengan kelas status (`bg-success-600`, dll) hanya pada elemen status spesifik (badge, indicator).
4. Gunakan `card-sem` + `surface-sem` untuk kartu utama; `surface-sem-alt` untuk blok di dalamnya.
5. Untuk teks: `text-sem-high` > `text-sem-mid` > `text-sem-dim` > `text-sem-muted` mengikuti hirarki visual.

### Contoh Konversi
Sebelum:
```html
<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
  <h3 class="text-gray-900 dark:text-gray-100">Judul</h3>
  <p class="text-gray-600 dark:text-gray-400">Deskripsi</p>
</div>
```
Sesudah:
```html
<div class="card-sem surface-sem border-sem p-6">
  <h3 class="text-sem-high">Judul</h3>
  <p class="text-sem-dim">Deskripsi</p>
</div>
```

### Penamaan Baru vs Lama
| Lama | Baru |
|------|------|
| `bg-white` / `dark:bg-gray-800` | `surface-sem` |
| `bg-gray-50` | `surface-sem-alt` |
| `text-gray-900` | `text-sem-high` |
| `text-gray-600` | `text-sem-mid` |
| `text-gray-500` | `text-sem-dim` |
| `border-gray-200` | `border-sem` |
| `border-gray-100` | `border-sem-subtle` |

### Roadmap
- [ ] Refactor komponen lama ke kelas semantik
- [ ] Audit konsistensi lintas modul
- [ ] Tambah varian density (`surface-sem-dense`)
- [ ] Ekspos token alias di dokumentasi publik

---
Pembaharuan ini memastikan onboarding UI lebih cepat, konsisten, dan siap multi tema.
