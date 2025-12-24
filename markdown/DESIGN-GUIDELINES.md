# 🎨 Panduan Design System Pusdokkes

## 📋 **Prinsip Dasar**

### 1. **Konsistensi Visual**
- Gunakan palet warna yang terbatas dan konsisten
- Terapkan typography hierarchy yang jelas
- Spacing yang teratur dan predictable

### 2. **Modular Component System**
- Setiap komponen punya tujuan yang spesifik
- Reusable dan maintainable
- Dokumentasi yang jelas

### 3. **Progressive Enhancement**
- Mobile-first approach
- Accessible by default
- Performance optimized

## 🎨 **Color Palette**

```css
/* Primary Colors - Pusdokkes Brand */
--primary-50: #eff6ff;
--primary-500: #3b82f6;
--primary-600: #2563eb;
--primary-700: #1d4ed8;

/* Secondary Colors - Police Colors */
--secondary-500: #374151;
--secondary-600: #4b5563;
--secondary-700: #1f2937;

/* Status Colors */
--success: #10b981;
--warning: #f59e0b;
--error: #ef4444;
--info: #3b82f6;

/* Neutral Colors */
--gray-50: #f9fafb;
--gray-100: #f3f4f6;
--gray-500: #6b7280;
--gray-900: #111827;
```

## 📐 **Spacing Scale**

```css
/* Consistent spacing using Tailwind's scale */
.space-xs { @apply p-1; }      /* 4px */
.space-sm { @apply p-2; }      /* 8px */
.space-md { @apply p-4; }      /* 16px */
.space-lg { @apply p-6; }      /* 24px */
.space-xl { @apply p-8; }      /* 32px */
.space-2xl { @apply p-12; }    /* 48px */
```

## 🔤 **Typography System**

```css
/* Heading Hierarchy */
.heading-1 { @apply text-3xl font-bold text-gray-900 leading-tight; }
.heading-2 { @apply text-2xl font-semibold text-gray-900 leading-tight; }
.heading-3 { @apply text-xl font-semibold text-gray-900 leading-snug; }
.heading-4 { @apply text-lg font-medium text-gray-900 leading-snug; }

/* Body Text */
.body-lg { @apply text-lg text-gray-700 leading-relaxed; }
.body-md { @apply text-base text-gray-700 leading-relaxed; }
.body-sm { @apply text-sm text-gray-600 leading-normal; }

/* Utility Text */
.text-muted { @apply text-gray-500; }
.text-caption { @apply text-xs text-gray-500 uppercase tracking-wide; }
```

## 🧩 **Component Library**

### **1. Buttons**
```html
<!-- Primary Actions -->
<button class="btn btn-primary">Primary Action</button>
<button class="btn btn-secondary">Secondary Action</button>
<button class="btn btn-outline">Outline Action</button>

<!-- Sizes -->
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary">Medium</button>
<button class="btn btn-primary btn-lg">Large</button>

<!-- States -->
<button class="btn btn-primary" disabled>Disabled</button>
<button class="btn btn-primary btn-loading">Loading...</button>
```

### **2. Cards**
```html
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Card Title</h3>
    <span class="card-subtitle">Optional subtitle</span>
  </div>
  <div class="card-body">
    <p>Card content goes here...</p>
  </div>
  <div class="card-footer">
    <button class="btn btn-primary">Action</button>
  </div>
</div>
```

### **3. Forms**
```html
<div class="form-group">
  <label class="form-label">Field Label</label>
  <input type="text" class="form-input" placeholder="Enter text...">
  <span class="form-help">Helper text</span>
  <span class="form-error">Error message</span>
</div>
```

### **4. Navigation**
```html
<nav class="navbar">
  <div class="navbar-brand">
    <img src="/logo.png" alt="Logo" class="navbar-logo">
    <span class="navbar-title">Pusdokkes</span>
  </div>
  <div class="navbar-nav">
    <a href="#" class="nav-link nav-link-active">Dashboard</a>
    <a href="#" class="nav-link">Permintaan</a>
  </div>
</nav>
```

## 📱 **Responsive Design**

### **Breakpoint Strategy**
```css
/* Mobile First */
.responsive-grid {
  @apply grid grid-cols-1;           /* Mobile: 1 column */
  @apply md:grid-cols-2;             /* Tablet: 2 columns */
  @apply lg:grid-cols-3;             /* Desktop: 3 columns */
  @apply xl:grid-cols-4;             /* Large: 4 columns */
}
```

### **Container Patterns**
```css
.container-sm { @apply max-w-2xl mx-auto px-4; }
.container-md { @apply max-w-4xl mx-auto px-4; }
.container-lg { @apply max-w-6xl mx-auto px-4; }
.container-xl { @apply max-w-7xl mx-auto px-4; }
```

## ♿ **Accessibility Guidelines**

### **1. Color Contrast**
- Minimum WCAG AA contrast ratio (4.5:1)
- Tidak hanya mengandalkan warna untuk informasi

### **2. Keyboard Navigation**
```css
.focus-visible {
  @apply focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2;
}
```

### **3. Screen Reader Support**
```html
<!-- Semantic HTML -->
<main role="main">
  <section aria-labelledby="dashboard-heading">
    <h1 id="dashboard-heading">Dashboard</h1>
  </section>
</main>

<!-- ARIA Labels -->
<button aria-label="Close dialog">×</button>
<input aria-describedby="help-text">
<span id="help-text">Enter your full name</span>
```

## 🔧 **Implementation Guidelines**

### **1. File Organization**
```
resources/views/
├── layouts/
│   ├── app.blade.php           # Main layout
│   ├── guest.blade.php         # Guest layout
│   └── navigation.blade.php    # Navigation component
├── components/
│   ├── button.blade.php        # Reusable button
│   ├── card.blade.php          # Card component
│   └── form/
│       ├── input.blade.php     # Form input
│       └── select.blade.php    # Form select
└── pages/
    ├── dashboard.blade.php
    └── requests/
        ├── index.blade.php
        └── create.blade.php
```

### **2. Component Naming Convention**
```php
// Blade Components
<x-button variant="primary" size="lg">Save</x-button>
<x-card title="Statistics">Content</x-card>
<x-form.input name="email" label="Email Address" />

// CSS Classes
.btn-{variant}-{size}     // .btn-primary-lg
.card-{type}              // .card-elevated
.form-{element}-{state}   // .form-input-error
```

### **3. State Management**
```css
/* Component States */
.is-loading { @apply opacity-50 pointer-events-none; }
.is-disabled { @apply opacity-60 cursor-not-allowed; }
.is-active { @apply bg-blue-50 text-blue-700; }
.is-error { @apply border-red-500 bg-red-50; }
.is-success { @apply border-green-500 bg-green-50; }
```

## 🚀 **Performance Best Practices**

### **1. CSS Optimization**
- Gunakan Tailwind purge untuk menghapus CSS yang tidak digunakan
- Hindari nested selectors yang dalam
- Minimize CSS-in-JS untuk komponen besar

### **2. Asset Loading**
```html
<!-- Preload critical fonts -->
<link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>

<!-- Optimize images -->
<img loading="lazy" src="/images/logo.webp" alt="Logo">
```

### **3. Code Splitting**
```javascript
// Lazy load heavy components
const DataTable = lazy(() => import('./components/DataTable'));
```

## 🧪 **Testing Strategy**

### **1. Visual Regression Testing**
- Screenshot testing untuk komponen UI
- Cross-browser compatibility testing

### **2. Accessibility Testing**
- Automated testing dengan axe-core
- Manual keyboard navigation testing
- Screen reader testing

### **3. Performance Testing**
- Lighthouse audits
- Bundle size monitoring
- Core Web Vitals tracking

## 📚 **Documentation**

### **1. Component Documentation**
Setiap komponen harus memiliki:
- Deskripsi fungsi dan penggunaan
- Props/parameters yang tersedia
- Contoh penggunaan
- Do's and Don'ts

### **2. Design Tokens Documentation**
- Color palette dengan use cases
- Typography scale dengan hierarchy
- Spacing system dengan examples

### **3. Pattern Library**
- Layout patterns
- Navigation patterns  
- Form patterns
- Data display patterns

## ✅ **Checklist Sebelum Deploy**

### **Design Review**
- [ ] Konsistensi visual across pages
- [ ] Responsive di semua device sizes
- [ ] Color contrast memenuhi WCAG AA
- [ ] Typography hierarchy jelas
- [ ] Spacing konsisten

### **Code Review**
- [ ] CSS tidak ada yang konflik
- [ ] Component reusability
- [ ] Performance optimized
- [ ] Accessibility compliant
- [ ] Cross-browser tested

### **User Experience**
- [ ] Intuitive navigation
- [ ] Clear error messages
- [ ] Loading states handled
- [ ] Empty states designed
- [ ] Success feedback provided

---

## 🎯 **Key Takeaways**

1. **Start Simple** - Gunakan Tailwind CSS sebagai foundation
2. **Be Consistent** - Establish design tokens dan stick to them
3. **Think Modular** - Build reusable components
4. **Test Early** - Accessibility dan performance dari awal
5. **Document Everything** - Future you will thank you

Dengan mengikuti panduan ini, website Anda akan:
- ✅ Terorganisir dan maintainable
- ✅ Konsisten across semua pages
- ✅ Accessible untuk semua users
- ✅ Performance optimized
- ✅ Easy to scale dan extend
