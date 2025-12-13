# settings-l10n-fix-serena.md
**Tujuan:** Membuat pengaturan **Zona Waktu, Format Tanggal, Bahasa (dan angka)** di halaman Settings **berfungsi & diterapkan global** tanpa merusak layout.  
**Alat:** Serena (context7). Jalankan blok perintah `serena` di bawah **secara berurutan**.

> Fokus: 1) simpan nilai locale → DB (sudah ada), 2) kirim data nested ke view (sudah), 3) **terapkan locale pada runtime** via middleware/provider, 4) sediakan opsi dropdown standar, 5) helper format tanggal/angka agar UI konsisten.

---

## 0) Aktifkan proyek & cek onboarding
```serena
activate_project {"name":"website"}
check_onboarding_performed {}
onboarding {}
```

---

## 1) Tambahkan Middleware penerapan Locale
Bikin middleware yang membaca `settings('locale.*')` lalu set **timezone**, **locale**, dan **Carbon** di setiap request web.

```serena
create_text_file {"path":"app/Http/Middleware/ApplyLocaleFromSettings.php","content":"<?php\nnamespace App\\Http\\Middleware;\n\nuse Closure;use Illuminate\\Http\\Request;use Carbon\\Carbon;use Illuminate\\Support\\Facades\\App;\n\nclass ApplyLocaleFromSettings\n{\n    public function handle(Request $request, Closure $next)\n    {\n        $tz  = settings('locale.timezone', config('app.timezone', 'UTC'));\n        $lang= settings('locale.language', config('app.locale', 'en'));\n\n        // Timezone\n        if ($tz) {\n            config(['app.timezone' => $tz]);\n            @date_default_timezone_set($tz);\n        }\n\n        // Bahasa / Locale\n        if ($lang) {\n            config(['app.locale' => $lang]);\n            App::setLocale($lang);\n            Carbon::setLocale($lang);\n        }\n\n        return $next($request);\n    }\n}\n"} 
```

Daftarkan ke **Kernel** pada group `web`.

```serena
replace_regex {"path":"app/Http/Kernel.php","pattern":"(protected\\s+\\$middlewareGroups\\s*=\\s*\\[\\s*'web'\\s*=>\\s*\\[)([\\s\\S]*?)(\\],)","repl":"$1$2\\n            \\\\App\\\\Http\\\\Middleware\\\\ApplyLocaleFromSettings::class,\\n        $3","count":1}
```

> Jika regex gagal (struktur berbeda), tambahkan manual baris `\App\Http\Middleware\ApplyLocaleFromSettings::class,` ke array group `'web'`.

---

## 2) Terapkan locale juga saat boot (CLI/queue)
Agar **queue/CLI** juga memakai setting terbaru saat start, set di `AppServiceProvider::boot`.

```serena
find_file {"relative_paths":["app/Providers"],"glob":"AppServiceProvider.php"}
read_file {"path":"app/Providers/AppServiceProvider.php"}
replace_regex {"path":"app/Providers/AppServiceProvider.php","pattern":"public function boot\\(\\)\\s*\\{","repl":"public function boot() {\\n        // Apply runtime locale/timezone from settings (also for CLI/queue)\\n        try {\\n            $tz = settings('locale.timezone', config('app.timezone','UTC'));\\n            $lg = settings('locale.language', config('app.locale','en'));\\n            if ($tz) { config(['app.timezone'=>$tz]); @date_default_timezone_set($tz); }\\n            if ($lg) { config(['app.locale'=>$lg]); app()->setLocale($lg); \\Carbon\\Carbon::setLocale($lg); }\\n        } catch (\\Throwable $e) { /* ignore during install */ }","count":1}
```

---

## 3) Opsi dropdown standar untuk view (kalau kosong)
Pastikan controller halaman **mengirim daftar opsi** supaya select tidak kosong.

```serena
create_text_file {"path":"app/Http/Controllers/SettingsPageController.php","content":"<?php\nnamespace App\\Http\\Controllers;use App\\Models\\SystemSetting;\nclass SettingsPageController extends Controller{public function index(){ $flat=SystemSetting::query()->get()->mapWithKeys(fn($r)=>[$r->key=>$r->value])->toArray(); $settings=settings_nest($flat); $options=['timezones'=>['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','UTC'],'date_formats'=>['DD/MM/YYYY','YYYY-MM-DD','DD-MM-YYYY'],'number_formats'=>['1.234,56','1,234.56'],'languages'=>['id','en'],'storage_drivers'=>['local','s3']]; return view('settings.index',compact('settings','options')); }}\n"} 
```

> Abaikan jika file Anda sudah memiliki opsi yang sama.

---

## 4) Helper format tanggal & angka (opsional tapi dianjurkan)
Supaya konsisten, buat helper kecil untuk tampilan.

```serena
find_file {"relative_paths":["app"],"glob":"helpers.php"}
insert_after_symbol {"file_path":"app/helpers.php","symbol_name":null,"content":"\nif (! function_exists('fmt_date')) {\n    function fmt_date($dt, $format=null) {\n        $format = $format ?? settings('locale.date_format','DD/MM/YYYY');\n        $c = \\Carbon\\Carbon::parse($dt);\n        // Map sederhana format populer ke PHP/Carbon\n        $map = ['DD/MM/YYYY'=>'d/m/Y','YYYY-MM-DD'=>'Y-m-d','DD-MM-YYYY'=>'d-m-Y'];\n        return $c->format($map[$format] ?? 'd/m/Y');\n    }\n}\n\nif (! function_exists('fmt_number')) {\n    function fmt_number($num, $decimals=2) {\n        $nf = settings('locale.number_format','1.234,56');\n        $dec = $nf === '1,234.56' ? '.' : ','; // decimal sep\n        $tho = $nf === '1,234.56' ? ',' : '.'; // thousand sep\n        return number_format((float)$num, $decimals, $dec, $tho);\n    }\n}\n"} 
```

> Pastikan `app/helpers.php` sudah di-autoload oleh composer. Jika belum: tambahkan `"files": ["app/helpers.php"]` ke `composer.json` (autoload) lalu `composer dump-autoload -o`.

---

## 5) Endpoint Save: validasi ringan & persist
Tambahkan validasi sederhana agar nilai masuk akal (opsional).

```serena
replace_regex {"path":"app/Http/Controllers/SettingsController.php","pattern":"public function update\\(Request \\$r\\) \\{([\\s\\S]*?)\\}","repl":"public function update(Request $r) {\\n        $incoming = $r->json()->all();\\n        if (!$incoming) { $incoming = json_decode((string)$r->input('payload','{}'), true) ?? []; }\\n        // Validasi ringan untuk locale\n        $allowedTz = ['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','UTC'];\n        $allowedDf = ['DD/MM/YYYY','YYYY-MM-DD','DD-MM-YYYY'];\n        $allowedNf = ['1.234,56','1,234.56'];\n        $allowedLg = ['id','en'];\n        if (isset($incoming['locale'])) {\n            $l = &$incoming['locale'];\n            if (isset($l['timezone']) && !in_array($l['timezone'],$allowedTz)) $l['timezone']='Asia/Jakarta';\n            if (isset($l['date_format']) && !in_array($l['date_format'],$allowedDf)) $l['date_format']='DD/MM/YYYY';\n            if (isset($l['number_format']) && !in_array($l['number_format'],$allowedNf)) $l['number_format']='1.234,56';\n            if (isset($l['language']) && !in_array($l['language'],$allowedLg)) $l['language']='id';\n        }\n        $flat = settings_flatten($incoming);\n        foreach ($flat as $key=>$value) { \\App\\Models\\SystemSetting::updateOrCreate(['key'=>$key],['value'=>$value,'updated_by'=>auth()->id()]); }\n        settings_forget_cache();\n        return response()->json(['ok'=>true]);\n    }","count":1}
```

---

## 6) Script Alpine: isi default & ikat ke select (tanpa ubah layout)
Tambahkan skrip di akhir **`resources/views/settings/index.blade.php`** agar select tidak kosong meski HTML-nya custom. Skrip ini **tidak mengubah struktur**; hanya memberikan `options` & binding jika dipakai.

```serena
insert_at_line {"path":"resources/views/settings/index.blade.php","line":99999,"content":"\n<script>\ndocument.addEventListener('alpine:init', () => {\n  Alpine.data('settingsPage', () => ({\n    form: window.formSettings ?? @js($settings),\n    options: @js($options ?? ['timezones'=>['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','UTC'],'date_formats'=>['DD/MM/YYYY','YYYY-MM-DD','DD-MM-YYYY'],'number_formats'=>['1.234,56','1,234.56'],'languages'=>['id','en'],'storage_drivers'=>['local','s3']]),\n    CSRF: document.querySelector('meta[name=csrf-token]').content,\n    // Helper: set default jika null\n    ensureDefaults(){\n      this.form.locale = this.form.locale || {};\n      this.form.locale.timezone = this.form.locale.timezone || this.options.timezones[0];\n      this.form.locale.date_format = this.form.locale.date_format || this.options.date_formats[0];\n      this.form.locale.number_format = this.form.locale.number_format || this.options.number_formats[0];\n      this.form.locale.language = this.form.locale.language || this.options.languages[0];\n    },\n    init(){ this.ensureDefaults(); },\n    save(){ fetch('{{ route('settings.update') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(this.form)}).then(r=>r.json()).then(()=>alert('Tersimpan')).catch(()=>alert('Gagal menyimpan')); }\n  }))\n})\n</script>\n"} 
```

> Jika Anda memakai `<select x-model=\"form.locale.timezone\">` dll, nilai sekarang akan **terisi**. Jika komponen select kustom, pastikan `x-model` mengikat ke `form.locale.*` yang sama.

---

## 7) Bersihkan cache & autoload
```serena
execute_shell_command {"command":"php artisan optimize:clear && composer dump-autoload -o"}
```

---

## 8) Verifikasi cepat
1. Buka `/settings` (admin).  
2. Pastikan dropdown **Zona Waktu / Format Tanggal / Bahasa / Format Angka** ada nilainya.  
3. Ubah timezone → klik **Simpan** → reload → tetap tersimpan.  
4. Coba `php artisan tinker` → `config('app.timezone')` & `app()->getLocale()` sesuai setting.  
5. Cek halaman lain yang menampilkan tanggal/angka → format mengikuti helper `fmt_date` / `fmt_number` (jika dipakai).

---

### Catatan
- Jika Anda menggunakan library tanggal lain (dayjs/moment) di FE, sinkronkan format dari `form.locale.date_format` saat render komponen FE.
- Untuk S3/local driver, pastikan nilai `retention.storage_driver` sudah dipakai oleh code penyimpanan file Anda (di luar scope L10N).

**Selesai.** Jalankan urutan perintah Serena di atas (context7). Setelah langkah 1–7, pengaturan **Zona Waktu, Tanggal & Bahasa** akan aktif di seluruh aplikasi.
