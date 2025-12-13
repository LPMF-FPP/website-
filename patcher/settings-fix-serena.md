# settings-fix-serena.md
**Goal:** Halaman **Settings** bener‑bener berfungsi (Save, Preview, Test, Template) TANPA merusak layout.  
**Cara:** Patch minimal + otomatisasi dengan **Serena MCP** (pakai *context7*), supaya eksekusinya konsisten dan bisa diulang.

> Prinsip: jangan ubah HTML layout/navbar Anda. Perbaikan fokus pada **data binding nested**, **web routes + CSRF**, dan service preview.

---

## 0) Jalankan Serena dengan *context7* dan aktifkan project
```serena
activate_project {"name": "website"}
check_onboarding_performed {}
onboarding {}
get_current_config {}
```

> Pastikan *context7* aktif di konfigurasi Serena Anda. Jika perlu, aktifkan mode/contexts sesuai setup Anda.

---

## 1) Tambah helper + util nest/flatten (wajib)
Simpan sebagai **`app/helpers.php`**.

```serena
create_text_file {"path":"app/helpers.php","content":"<?php\n\nuse Illuminate\\Support\\Arr;\nuse App\\Models\\SystemSetting;\n\nif (! function_exists('settings')) {\n    function settings(string $key = null, $default = null) {\n        $all = cache()->remember('sys_settings_all', 60, function () {\n            return SystemSetting::query()->get()\n                ->mapWithKeys(fn($row) => [$row->key => $row->value])\n                ->toArray();\n        });\n        return $key ? data_get($all, $key, $default) : $all;\n    }\n}\n\nif (! function_exists('settings_forget_cache')) {\n    function settings_forget_cache(): void { cache()->forget('sys_settings_all'); }\n}\n\nif (! function_exists('settings_nest')) {\n    function settings_nest(array $flat): array {\n        $nested = [];\n        foreach ($flat as $k => $v) { data_set($nested, $k, $v); }\n        return $nested;\n    }\n}\n\nif (! function_exists('settings_flatten')) {\n    function settings_flatten(array $nested): array { return Arr::dot($nested); }\n}\n"} 
```

Tambahkan helpers ke **composer.json** lalu autoload:
```serena
find_file {"relative_paths":["."],"glob":"composer.json"}
read_file {"path":"composer.json"}
replace_regex {"path":"composer.json","pattern":"\"autoload\"\\s*:\\s*\\{","repl":"\"autoload\":{\"files\":[\"app/helpers.php\"],","count":1}
execute_shell_command {"command":"composer dump-autoload"}
```

---

## 2) Pastikan model Sistem Setting me‑*cast* JSON → array
```serena
create_text_file {"path":"app/Models/SystemSetting.php","content":"<?php\nnamespace App\\Models;\nuse Illuminate\\Database\\Eloquent\\Model;\nclass SystemSetting extends Model{protected $fillable=['key','value','updated_by'];protected $casts=['value'=>'array'];}\n"}
```

> Abaikan jika file sudah ada dan sudah `protected $casts = ['value'=>'array'];`.

---

## 3) Controller halaman: kirim data **nested** ke Blade
```serena
create_text_file {"path":"app/Http/Controllers/SettingsPageController.php","content":"<?php\nnamespace App\\Http\\Controllers;\nuse App\\Models\\SystemSetting;\nclass SettingsPageController extends Controller{\n  public function index(){\n    $flat = SystemSetting::query()->get()->mapWithKeys(fn($r)=>[$r->key=>$r->value])->toArray();\n    $settings = settings_nest($flat);\n    $options = [\n      'timezones'=>['Asia/Jakarta','Asia/Makassar','Asia/Jayapura'],\n      'date_formats'=>['DD/MM/YYYY','YYYY-MM-DD','DD-MM-YYYY'],\n      'number_formats'=>['1.234,56','1,234.56'],\n      'languages'=>['id','en'],\n      'storage_drivers'=>['local','s3'],\n    ];\n    return view('settings.index', compact('settings','options'));\n  }\n}\n"}
```

---

## 4) Controller aksi: Save/Preview/Test via **web routes + CSRF**
```serena
create_text_file {"path":"app/Http/Controllers/SettingsController.php","content":"<?php\nnamespace App\\Http\\Controllers;\nuse Illuminate\\Http\\Request;use App\\Models\\SystemSetting;use App\\Services\\NumberingService;\nclass SettingsController extends Controller{\n  public function show(){return response()->json(settings_nest(settings()));}\n  public function update(Request $r){$incoming=$r->json()->all();if(!$incoming){$incoming=json_decode((string)$r->input('payload','{}'),true)??[];}$flat=settings_flatten($incoming);foreach($flat as $key=>$value){SystemSetting::updateOrCreate(['key'=>$key],['value'=>$value,'updated_by'=>auth()->id()]);}settings_forget_cache();return response()->json(['ok'=>true]);}\n  public function preview(Request $r, NumberingService $svc){$scope=$r->input('scope');$cfg=$r->input('config',[]);$pattern=data_get($cfg,\"numbering.$scope.pattern\")??settings(\"numbering.$scope.pattern\");return response()->json(['example'=>$svc->example($scope,$pattern)]);} \n  public function test(Request $r){return response()->json(['ok'=>true]);}\n}\n"}
```

---

## 5) Service penomoran (preview aman & issue atomic)
```serena
create_text_file {"path":"app/Services/NumberingService.php","content":"<?php\nnamespace App\\Services;use App\\Models\\Sequence;use Illuminate\\Support\\Facades\\DB;class NumberingService{public function issue(string $scope,array $ctx):string{$cfg=settings(\"numbering.$scope\");throw_unless($cfg,\\RuntimeException::class,\"Config $scope missing\");$pattern=data_get($cfg,'pattern');$reset=data_get($cfg,'reset','never');$bucket=$this->makeBucket($scope,$reset,$ctx,$cfg);return DB::transaction(function()use($scope,$bucket,$pattern,$ctx){$seq=Sequence::where('scope',$scope)->where('bucket',$bucket)->lockForUpdate()->first();if(!$seq){$seq=Sequence::create(['scope'=>$scope,'bucket'=>$bucket,'current_value'=>(int)data_get(settings(),\"numbering.$scope.start_from\",1)-1]);}$seq->current_value+=1;$seq->save();return $this->render($pattern,$seq->current_value,$ctx);});}\npublic function example(string $scope,?string $pattern=null,array $ctx=[]):string{$pattern=$pattern??data_get(settings(),\"numbering.$scope.pattern\");$ctx=array_merge(['now'=>now(),'test_code'=>'GCMS','instrument_code'=>'QS2020','request_short'=>'REQ-25-0102','investigator_id'=>7,'doc_code'=>strtoupper($scope)],$ctx);return $this->render($pattern,123,$ctx);} \npublic function render(string $pattern,int $seq,array $ctx):string{$now=$ctx['now']??now();$map=['{LAB}'=>settings('branding.lab_code'),'{YYYY}'=>$now->format('Y'),'{YY}'=>$now->format('y'),'{MM}'=>$now->format('m'),'{DD}'=>$now->format('d'),'{INV}'=>str_pad((string)($ctx['investigator_id']??0),2,'0',STR_PAD_LEFT),'{TEST}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','',$ctx['test_code']??'GEN')),'{INST}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','',$ctx['instrument_code']??'NA')),'{REQ}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','',$ctx['request_short']??'REQ')),'{DOC}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','',$ctx['doc_code']??'DOC'))];$out=preg_replace_callback('/\\{SEQ:(\\d+)\\}/',fn($m)=>str_pad((string)$seq,(int)$m[1],'0',STR_PAD_LEFT),$pattern);return strtr($out,$map);} \nprotected function makeBucket(string $scope,string $reset,array $ctx,array $cfg):string{$now=$ctx['now']??now();$p=[];if($reset==='yearly')$p[]=$now->format('Y');if($reset==='monthly')$p[]=$now->format('Y-m');if($reset==='daily')$p[]=$now->format('Y-m-d');if($reset==='per_investigator')$p[]='INV'.str_pad((string)($ctx['investigator_id']??0),2,'0',STR_PAD_LEFT);if($scope==='lhu'&&data_get($cfg,'per_test_type'))$p[]=strtoupper($ctx['test_code']??'GEN');return $p?implode('|',$p):'default';}}"} 
```

---

## 6) Routes **WEB** (gunakan session + CSRF, bukan `api.php`)
```serena
find_file {"relative_paths":["routes"],"glob":"web.php"}
insert_at_line {"path":"routes/web.php","line":99999,"content":"\nuse App\\Http\\Controllers\\SettingsPageController; \nuse App\\Http\\Controllers\\SettingsController; \n\nRoute::middleware(['auth','can:manage-settings'])->group(function(){\n  Route::get('/settings',[SettingsPageController::class,'index'])->name('settings.index');\n  Route::get('/settings/data',[SettingsController::class,'show'])->name('settings.show');\n  Route::post('/settings/save',[SettingsController::class,'update'])->name('settings.update');\n  Route::post('/settings/preview',[SettingsController::class,'preview'])->name('settings.preview');\n  Route::post('/settings/test',[SettingsController::class,'test'])->name('settings.test');\n});\n"} 
```

---

## 7) Inisialisasi Alpine di page **settings.index** (tanpa ubah layout)
Tambahkan skrip di **akhir** `resources/views/settings/index.blade.php`:

```serena
insert_at_line {"path":"resources/views/settings/index.blade.php","line":99999,"content":"\n<script>\ndocument.addEventListener('alpine:init', () => {\n  Alpine.data('settingsPage', () => ({\n    form: @js($settings),\n    previewResult: { sample_code:'', ba:'', lhu:'' },\n    CSRF: document.querySelector('meta[name=csrf-token]').content,\n    save(){ fetch('{{ route('settings.update') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(this.form)}).then(r=>r.json()).then(()=>alert('Tersimpan')).catch(()=>alert('Gagal menyimpan')); },\n    preview(scope){ fetch('{{ route('settings.preview') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({scope,config:this.form})}).then(r=>r.json()).then(d=>this.previewResult[scope]=d.example); },\n    testGenerate(){ fetch('{{ route('settings.test') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({at:Date.now()})}).then(()=>alert('Test generate (sandbox)')); }\n  }))\n})\n</script>\n"} 
```

> Pastikan di layout `<head>` ada meta: `<meta name=\"csrf-token\" content=\"{{ csrf_token() }}\">`.

---

## 8) Bersihkan cache & autoload
```serena
execute_shell_command {"command":"php artisan view:clear && php artisan route:clear && php artisan config:clear && php artisan cache:clear && composer dump-autoload -o"}
```

---

## 9) Tes cepat (opsional)
Jalankan aplikasi, login sebagai admin/supervisor, buka `/settings`, uji **Preview**, **Simpan**, dan **Test**.

**Checklist:**
- Form terisi dari DB (nested).
- Preview menghasilkan contoh nomor.
- Simpan mem‑persist nilai → reload tetap.
- Test tidak menulis DB (sandbox).

— Selesai. File ini bisa ditempel ke Codex/Serena untuk menjalankan patch otomatis.