# settings-fix-context.md

## 🎯 Target
Perbaiki Halaman **Settings** LIMS agar:
- Data binding bekerja (form terisi & bisa disimpan).
- Tombol **Preview** dan **Test Generate** berfungsi.
- Tidak merusak layout/navbar/dropdown yang sudah ada.
- RBAC/CSRF aman.
- Ada **tes otomatis** dan watcher untuk menjalankan tes berulang sampai bug hilang.

> ⚠️ **Prinsip:** _Surgical patch_. Jangan ubah markup/HTML layout yang sudah ada selain menyisipkan skrip inisialisasi di halaman Settings. Backend memakai **web routes + CSRF**, bukan `api.php`.

---

## 1) Helper + Model (wajib)

### 1.1 `app/helpers.php`
> Tambah helper settings + util **nest/flatten**. Autoload pada composer (lihat bagian 6).

```php
<?php

use Illuminate\Support\Arr;
use App\Models\SystemSetting;

if (! function_exists('settings')) {
    function settings(string $key = null, $default = null) {
        $all = cache()->remember('sys_settings_all', 60, function () {
            return SystemSetting::query()->get()
                ->mapWithKeys(fn($row) => [$row->key => $row->value])
                ->toArray();
        });
        return $key ? data_get($all, $key, $default) : $all;
    }
}

if (! function_exists('settings_forget_cache')) {
    function settings_forget_cache(): void { cache()->forget('sys_settings_all'); }
}

if (! function_exists('settings_nest')) {
    function settings_nest(array $flat): array {
        $nested = [];
        foreach ($flat as $k => $v) { data_set($nested, $k, $v); }
        return $nested;
    }
}

if (! function_exists('settings_flatten')) {
    function settings_flatten(array $nested): array { return Arr::dot($nested); }
}
```

### 1.2 `app/Models/SystemSetting.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key','value','updated_by'];
    protected $casts = ['value' => 'array']; // JSON -> array
}
```

---

## 2) Controller Halaman (kirim data **nested** ke Blade)

### 2.1 `app/Http/Controllers/SettingsPageController.php`
```php
<?php
namespace App\Http\Controllers;

use App\Models\SystemSetting;

class SettingsPageController extends Controller
{
    public function index()
    {
        $flat = SystemSetting::query()->get()
            ->mapWithKeys(fn($r) => [$r->key => $r->value])
            ->toArray();

        $settings = settings_nest($flat); // kunci: kirim nested, bukan key bertitik

        $options = [
            'timezones'       => ['Asia/Jakarta','Asia/Makassar','Asia/Jayapura'],
            'date_formats'    => ['DD/MM/YYYY','YYYY-MM-DD','DD-MM-YYYY'],
            'number_formats'  => ['1.234,56','1,234.56'],
            'languages'       => ['id','en'],
            'storage_drivers' => ['local','s3'],
        };

        return view('settings.index', compact('settings','options'));
    }
}
```

---

## 3) Controller Aksi (Save/Preview/Test) — **web routes + CSRF**

### 3.1 `app/Http/Controllers/SettingsController.php`
```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SystemSetting;
use App\Services\NumberingService;

class SettingsController extends Controller
{
    public function show() {
        return response()->json(settings_nest(settings()));
    }

    public function update(Request $r) {
        $incoming = $r->json()->all();
        if (!$incoming) { $incoming = json_decode((string)$r->input('payload','{}'), true) ?? []; }

        $flat = settings_flatten($incoming); // simpan kembali sebagai key bertitik

        foreach ($flat as $key => $value) {
            SystemSetting::updateOrCreate(['key'=>$key], [
                'value'=>$value, 'updated_by'=>auth()->id()
            ]);
        }

        settings_forget_cache();
        return response()->json(['ok'=>true]);
    }

    public function preview(Request $r, NumberingService $svc) {
        $scope   = $r->input('scope');
        $config  = $r->input('config', []);
        $pattern = data_get($config, "numbering.$scope.pattern") ?? settings("numbering.$scope.pattern");
        return response()->json(['example'=>$svc->example($scope, $pattern)]);
    }

    public function test(Request $r) {
        return response()->json(['ok'=>true]); // sandbox; tidak menyentuh sequences
    }
}
```

---

## 4) Service (preview & issue; aman race condition)

### 4.1 `app/Services/NumberingService.php`
```php
<?php
namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    public function issue(string $scope, array $ctx): string
    {
        $cfg = settings("numbering.$scope");
        throw_unless($cfg, \RuntimeException::class, "Config $scope missing");

        $pattern = data_get($cfg,'pattern');
        $reset   = data_get($cfg,'reset','never');
        $bucket  = $this->makeBucket($scope,$reset,$ctx,$cfg);

        return DB::transaction(function() use($scope,$bucket,$pattern,$ctx,$cfg){
            $seq = Sequence::where('scope',$scope)->where('bucket',$bucket)
                   ->lockForUpdate()->first();
            if (!$seq) {
                $seq = Sequence::create([
                    'scope'=>$scope, 'bucket'=>$bucket,
                    'current_value'=>(int)data_get($cfg,'start_from',1)-1
                ]);
            }
            $seq->current_value += 1; $seq->save();
            return $this->render($pattern, $seq->current_value, $ctx);
        });
    }

    public function example(string $scope, ?string $pattern=null, array $ctx=[]): string
    {
        $pattern = $pattern ?? data_get(settings(), "numbering.$scope.pattern");
        $ctx = array_merge([
            'now'=>now(),'test_code'=>'GCMS','instrument_code'=>'QS2020',
            'request_short'=>'REQ-25-0102','investigator_id'=>7,'doc_code'=>strtoupper($scope)
        ], $ctx);
        return $this->render($pattern, 123, $ctx);
    }

    public function render(string $pattern, int $seq, array $ctx): string
    {
        $now = $ctx['now'] ?? now();
        $map = [
            '{LAB}'  => settings('branding.lab_code'),
            '{YYYY}' => $now->format('Y'), '{YY}'=>$now->format('y'),
            '{MM}'   => $now->format('m'), '{DD}' => $now->format('d'),
            '{INV}'  => str_pad((string)($ctx['investigator_id']??0),2,'0',STR_PAD_LEFT),
            '{TEST}' => strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['test_code']??'GEN')),
            '{INST}' => strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['instrument_code']??'NA')),
            '{REQ}'  => strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['request_short']??'REQ')),
            '{DOC}'  => strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['doc_code']??'DOC')),
        ];
        $out = preg_replace_callback('/\{SEQ:(\d+)\}/',
            fn($m)=> str_pad((string)$seq,(int)$m[1],'0',STR_PAD_LEFT), $pattern);
        return strtr($out, $map);
    }

    protected function makeBucket(string $scope, string $reset, array $ctx, array $cfg): string
    {
        $now = $ctx['now'] ?? now(); $p=[];
        if ($reset==='yearly')   $p[]=$now->format('Y');
        if ($reset==='monthly')  $p[]=$now->format('Y-m');
        if ($reset==='daily')    $p[]=$now->format('Y-m-d');
        if ($reset==='per_investigator') $p[]='INV'.str_pad((string)($ctx['investigator_id']??0),2,'0',STR_PAD_LEFT);
        if ($scope==='lhu' && data_get($cfg,'per_test_type')) $p[]=strtoupper($ctx['test_code']??'GEN');
        return $p ? implode('|',$p) : 'default';
    }
}
```

---

## 5) Routes **WEB** (bukan `api.php`)
```php
// routes/web.php
use App\Http\Controllers\SettingsPageController;
use App\Http\Controllers\SettingsController;

Route::middleware(['auth','can:manage-settings'])->group(function () {
    Route::get('/settings', [SettingsPageController::class,'index'])->name('settings.index');
    Route::get('/settings/data', [SettingsController::class,'show'])->name('settings.show');
    Route::post('/settings/save', [SettingsController::class,'update'])->name('settings.update');
    Route::post('/settings/preview', [SettingsController::class,'preview'])->name('settings.preview');
    Route::post('/settings/test', [SettingsController::class,'test'])->name('settings.test');
});
```

---

## 6) Composer autoload & cache
Tambahkan ke `composer.json`:
```json
"autoload": { "files": [ "app/helpers.php" ] }
```
Lalu:
```bash
composer dump-autoload
php artisan view:clear && php artisan route:clear && php artisan config:clear && php artisan cache:clear
```

---

## 7) Blade Settings — **tanpa ubah layout**, hanya inisialisasi & aksi
Di `resources/views/settings/index.blade.php`:
1) Pastikan meta CSRF ada di `<head>` layout:  
`<meta name="csrf-token" content="{{ csrf_token() }}">`

2) Tambahkan skrip Alpine berikut **di akhir halaman** (binding ke data nested yang dikirim controller). Jangan ubah markup form Anda.

```html
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('settingsPage', () => ({
    form: @js($settings),
    previewResult: { sample_code:'', ba:'', lhu:'' },
    CSRF: document.querySelector('meta[name=csrf-token]').content,

    save() {
      fetch("{{ route('settings.update') }}", {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': this.CSRF,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(this.form)
      }).then(r => r.json()).then(() => alert('Tersimpan')).catch(()=>alert('Gagal menyimpan'));
    },

    preview(scope) {
      fetch("{{ route('settings.preview') }}", {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': this.CSRF,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ scope, config: this.form })
      }).then(r => r.json()).then(d => this.previewResult[scope]=d.example);
    },

    testGenerate() {
      fetch("{{ route('settings.test') }}", {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': this.CSRF,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ at: Date.now() })
      }).then(()=> alert('Test generate (sandbox) dicatat'));
    }
  }))
})
</script>
```

> Binding contoh: `x-model="form.numbering.sample_code.pattern"` akan **langsung hidup** karena `form` sekarang nested.

---

## 8) Tes Otomatis (Pest) — jalankan berulang sampai hijau

### 8.1 Install (jika belum)
```bash
composer require pestphp/pest --dev
php artisan pest:install
```

### 8.2 `tests/Feature/SettingsPageTest.php`
```php
<?php

use App\Models\User;
use App\Models\SystemSetting;

beforeEach(function () {
    SystemSetting::updateOrCreate(['key'=>'branding'], ['value'=>['lab_code'=>'LPMF']]);
    SystemSetting::updateOrCreate(['key'=>'numbering.sample_code'], ['value'=>['pattern'=>'LPMF-{YYYY}{MM}-{INV}-{SEQ:4}','reset'=>'monthly','start_from'=>1]]);
    SystemSetting::updateOrCreate(['key'=>'numbering.ba'], ['value'=>['pattern'=>'BA/{YYYY}/{MM}/{SEQ:4}','reset'=>'monthly','start_from'=>1]]);
    SystemSetting::updateOrCreate(['key'=>'numbering.lhu'], ['value'=>['pattern'=>'LHU/{YYYY}/{MM}/{TEST}/{SEQ:4}','reset'=>'monthly','start_from'=>1,'per_test_type'=>true]]);
    cache()->forget('sys_settings_all');
});

test('settings page loads', function () {
    $user = User::factory()->create(['role'=>'admin']);
    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Settings', false);
});

test('preview returns example', function () {
    $user = User::factory()->create(['role'=>'admin']);
    $this->actingAs($user)
        ->post(route('settings.preview'), ['scope'=>'lhu','config'=>[]])
        ->assertOk()
        ->assertJsonStructure(['example']);
});

test('save roundtrip', function () {
    $user = User::factory()->create(['role'=>'admin']);
    $payload = ['numbering'=>['ba'=>['pattern'=>'BA/{YYYY}/{SEQ:5}','reset'=>'yearly','start_from'=>9]]];
    $this->actingAs($user)
        ->postJson(route('settings.update'), $payload)
        ->assertOk();
    cache()->forget('sys_settings_all');
    $this->assertSame('BA/{YYYY}/{SEQ:5}', data_get(settings(),'numbering.ba.pattern'));
});
```

### 8.3 Watch mode
Tambahkan ke `package.json`:
```json
{
  "scripts": {
    "test:php": "vendor/bin/pest --parallel --stop-on-failure",
    "test:php:watch": "vendor/bin/pest --watch"
  }
}
```
Jalankan:
```bash
npm run test:php:watch
```

---

## 9) Checklist verifikasi manual
1. Buka `/settings` sebagai admin/supervisor → form terisi (bukan kosong).
2. Ubah pattern `numbering.lhu.pattern` → klik **Preview** → contoh muncul.
3. Klik **Simpan** → reload → nilai tetap.
4. Klik **Test Generate** → alert muncul.
5. User non-admin → `/settings` terblokir (403).

---

## 10) Copilot hint
> “Jangan ubah HTML layout. Tambahkan helper `settings()` + nest/flatten, cast JSON `SystemSetting`, controller halaman mengirim **nested** settings ke Blade, routes **web** untuk `/settings/save|preview|test` dengan CSRF, dan skrip Alpine untuk Save/Preview/Test. Tambahkan tes Pest feature (load page, preview, save roundtrip) dan jalankan `vendor/bin/pest --watch` hingga semua tes hijau.”
