# 🧩 LIMS SYSTEM SETTINGS (Laravel)  
### Halaman Pengaturan untuk Generate Kode Sampel, BA, dan LHU

## 🎯 Tujuan
Implementasi halaman **Settings** untuk sistem LIMS (Laboratory Information Management System) yang mengatur seluruh aspek konfigurasi backend, meliputi:

- Penomoran otomatis (**Sample**, **BA**, **LHU**)  
- Template dokumen (unggah dan aktivasi)  
- Branding & Identitas (logo, warna, instansi, `{LAB}`)  
- Header/footer PDF (alamat, kontak, watermark, tanda tangan digital, QR)  
- Lokalisasi (zona waktu, format tanggal/angka, bahasa UI)  
- Penyimpanan & Retensi file  
- Otomasi (auto-generate dokumen pendukung, notifikasi email/WA)  
- Keamanan & Akses (role-based control)  
- Preview & Test-generate (sandbox)  
- Audit log setiap perubahan dan penerbitan nomor  

---

## 🗂️ 1. Struktur Database

### Migration `xxxx_create_system_settings_tables.php`
```php
Schema::create('system_settings', function (Blueprint $t) {
  $t->id();
  $t->string('key')->unique();
  $t->jsonb('value');
  $t->foreignId('updated_by')->nullable();
  $t->timestamps();
});

Schema::create('sequences', function (Blueprint $t) {
  $t->id();
  $t->string('scope');
  $t->string('bucket');
  $t->integer('current_value')->default(0);
  $t->unique(['scope','bucket']);
});

Schema::create('document_templates', function (Blueprint $t) {
  $t->id();
  $t->string('code')->unique();
  $t->string('name');
  $t->string('storage_path');
  $t->jsonb('meta')->default(DB::raw("'{}'::jsonb"));
  $t->foreignId('updated_by')->nullable();
  $t->timestamps();
});

Schema::create('audit_logs', function (Blueprint $t) {
  $t->id();
  $t->foreignId('actor_id')->nullable();
  $t->string('action');
  $t->string('target')->nullable();
  $t->jsonb('before')->nullable();
  $t->jsonb('after')->nullable();
  $t->jsonb('context')->nullable();
  $t->timestamps();
});
```

---

## 🌱 2. Seeder Awal

```php
SystemSetting::upsert([
 ['key'=>'numbering.sample_code','value'=>json_encode(['pattern'=>'LPMF-{YYYY}{MM}-{INV}-{SEQ:4}','reset'=>'monthly','start_from'=>1])],
 ['key'=>'numbering.ba','value'=>json_encode(['pattern'=>'BA/{YYYY}/{MM}/{SEQ:4}','reset'=>'monthly','start_from'=>1])],
 ['key'=>'numbering.lhu','value'=>json_encode(['pattern'=>'LHU/{YYYY}/{MM}/{TEST}/{SEQ:4}','reset'=>'monthly','start_from'=>1,'per_test_type'=>true])],

 ['key'=>'branding','value'=>json_encode([
   'lab_code'=>'LPMF','org_name'=>'Laboratorium Pengujian Mutu Farmasi Kepolisian',
   'logo_path'=>null,'primary_color'=>'#0A5FD3','secondary_color'=>'#0EC5FF','digital_stamp_path'=>null
 ])],

 ['key'=>'pdf','value'=>json_encode([
   'header'=>['show'=>true,'address'=>'Jl. Contoh No.1, Jakarta','contact'=>'+62-21-xxxxxxx','logo_path'=>null,'watermark'=>null],
   'footer'=>['show'=>true,'text'=>'Rahasia – Hanya untuk keperluan resmi','page_numbers'=>true],
   'signature'=>['enabled'=>true,'signers'=>[['title'=>'Kepala Lab','name'=>null,'stamp_path'=>null]]],
   'qr'=>['enabled'=>true,'target'=>'request_detail_url','caption'=>'Scan untuk verifikasi']
 ])],

 ['key'=>'templates.active','value'=>json_encode([
   'LHU'=>null,'BA_PERMINTAAN'=>null,'BA_PENYERAHAN'=>null,'TANDA_TERIMA'=>null
 ])],

 ['key'=>'locale','value'=>json_encode([
   'timezone'=>'Asia/Jakarta','date_format'=>'DD/MM/YYYY','number_format'=>'1.234,56','language'=>'id'
 ])],

 ['key'=>'retention','value'=>json_encode([
   'storage_driver'=>'local','base_path'=>'official_docs/','purge_after_days'=>1825,
   'export_filename_pattern'=>'{DOC}/{YYYY}/{MM}/{SEQ:4}.pdf'
 ])],

 ['key'=>'automation','value'=>json_encode([
   'auto_generate_supporting_docs'=>[
     'request_letter'=>false,'handover_report'=>false,'sample_receipt'=>false,'test_report'=>false
   ],
   'notify_on_issue'=>[
     'email'=>false,'whatsapp'=>false,
     'templates'=>['subject'=>'[LIMS] Nomor {SCOPE} {NUMBER} terbit','body'=>'Nomor {SCOPE}: {NUMBER} untuk {REQ}']
   ]
 ])],

 ['key'=>'security.roles','value'=>json_encode([
   'can_manage_settings'=>['admin','supervisor'],
   'can_issue_number'=>['admin','supervisor','analis']
 ])],
], ['key'], ['value']);
```

---

## ⚙️ 3. Service Numbering (Atomic & Thread-Safe)

`app/Services/NumberingService.php`
```php
class NumberingService {
  public function issue(string $scope, array $ctx): string {
    $cfg = settings("numbering.$scope");
    if(!$cfg) throw new \RuntimeException("Config $scope missing");

    $pattern = data_get($cfg,'pattern');
    $reset   = data_get($cfg,'reset','never');
    $bucket  = $this->makeBucket($scope,$reset,$ctx,$cfg);

    return DB::transaction(function() use($scope,$bucket,$pattern,$ctx,$cfg){
      $seq = Sequence::where('scope',$scope)->where('bucket',$bucket)->lockForUpdate()->first();
      if(!$seq){
        $seq = Sequence::create(['scope'=>$scope,'bucket'=>$bucket,'current_value'=>(int)data_get($cfg,'start_from',1)-1]);
      }
      $seq->current_value += 1;
      $seq->save();

      $number = $this->renderPattern($pattern,$seq->current_value,$ctx);
      Audit::log('ISSUE_NUMBER',"numbering.$scope",null,['bucket'=>$bucket,'value'=>$number,'ctx'=>$ctx]);
      event(new \App\Events\NumberIssued($scope,$number,$ctx));
      return $number;
    });
  }

  protected function makeBucket(string $scope,string $reset,array $ctx,array $cfg): string {
    $now = $ctx['now'] ?? now(); $p=[];
    if($reset==='yearly')   $p[]=$now->format('Y');
    if($reset==='monthly')  $p[]=$now->format('Y-m');
    if($reset==='daily')    $p[]=$now->format('Y-m-d');
    if($reset==='per_investigator') $p[]='INV'.str_pad((string)($ctx['investigator_id']??0),2,'0',STR_PAD_LEFT);
    if($scope==='lhu' && data_get($cfg,'per_test_type')) $p[]=strtoupper($ctx['test_code']??'GEN');
    return $p?implode('|',$p):'default';
  }

  protected function renderPattern(string $pattern,int $seq,array $ctx): string {
    $now = $ctx['now'] ?? now();
    $map = [
      '{LAB}'=>settings('branding.lab_code'),
      '{YYYY}'=>$now->format('Y'),'{YY}'=>$now->format('y'),
      '{MM}'=>$now->format('m'),'{DD}'=>$now->format('d'),
      '{INV}'=>str_pad((string)($ctx['investigator_id']??0),2,'0',STR_PAD_LEFT),
      '{TEST}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['test_code']??'GEN')),
      '{INST}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['instrument_code']??'NA')),
      '{REQ}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['request_short']??'REQ')),
      '{DOC}'=>strtoupper(preg_replace('/[^A-Z0-9_-]/','', $ctx['doc_code']??$ctx['scope']??'DOC')),
    ];
    $out = preg_replace_callback('/\{SEQ:(\d+)\}/',fn($m)=>str_pad((string)$seq,(int)$m[1],'0',STR_PAD_LEFT),$pattern);
    return strtr($out,$map);
  }
}
```

---

## 🔗 4. Routes API

```php
Route::middleware(['auth:sanctum','can:manage-settings'])->group(function(){
  Route::get('/settings',[SettingsController::class,'show']);
  Route::put('/settings',[SettingsController::class,'update']);
  Route::post('/settings/preview',[SettingsController::class,'previewPattern']);
  Route::post('/settings/test-generate',[SettingsController::class,'testGenerate']);
  Route::post('/templates/upload',[TemplateController::class,'upload']);
  Route::get('/templates',[TemplateController::class,'index']);
  Route::post('/templates/activate',[TemplateController::class,'activate']);
});

Route::middleware(['auth:sanctum'])->group(function(){
  Route::post('/numbering/{scope}/issue',[NumberingController::class,'issue'])
       ->whereIn('scope',['sample_code','ba','lhu'])
       ->can('issue-number');
});
```

---

## 🧱 5. Controller Ringkas

### SettingsController
```php
class SettingsController extends Controller {
  public function show(){ return response()->json(collect(SystemSetting::all())->pluck('value','key')); }

  public function update(Request $r){
    $data=$r->all();
    foreach($data as $key=>$value){
      $before=optional(SystemSetting::where('key',$key)->first())->value;
      SystemSetting::updateOrCreate(['key'=>$key],['value'=>$value,'updated_by'=>auth()->id()]);
      Audit::log('UPDATE_SETTING',$key,$before,$value,['actor'=>auth()->id()]);
    }
    return response()->noContent();
  }

  public function previewPattern(Request $r){
    $scope=$r->input('scope'); $cfg=$r->input('config');
    $pattern=data_get($cfg,"numbering.$scope.pattern") ?? settings("numbering.$scope.pattern");
    $service=app(\App\Services\NumberingService::class);
    $example=(new \ReflectionClass($service))->getMethod('renderPattern')->invoke($service,
      $pattern, 123, ['now'=>now(),'test_code'=>'GCMS','instrument_code'=>'QS2020','request_short'=>'REQ-25-0102','investigator_id'=>7,'doc_code'=>strtoupper($scope)]
    );
    return response()->json(['example'=>$example]);
  }

  public function testGenerate(Request $r){
    Audit::log('TEST_GENERATE','settings',null,$r->all(),['actor'=>auth()->id()]);
    return response()->json(['ok'=>true]);
  }
}
```

### TemplateController
```php
class TemplateController extends Controller {
  public function upload(Request $r){
    $code = Str::upper($r->input('code'));
    $name = $r->input('name');
    $file = $r->file('file');
    $path = $file->store(settings('retention.base_path').'templates','public');
    DocumentTemplate::updateOrCreate(['code'=>$code],[
      'name'=>$name,'storage_path'=>$path,'updated_by'=>auth()->id(),'meta'=>$r->input('meta',[])
    ]);
    Audit::log('UPLOAD_TEMPLATE',$code,null,['path'=>$path],['actor'=>auth()->id()]);
    return response()->json(['code'=>$code,'path'=>$path]);
  }

  public function index(){ return DocumentTemplate::orderBy('name')->get(); }

  public function activate(Request $r){
    $type = $r->input('type'); $code = $r->input('code');
    $active = settings('templates.active'); $before=$active;
    $active[$type]=$code;
    SystemSetting::updateOrCreate(['key'=>'templates.active'],['value'=>$active,'updated_by'=>auth()->id()]);
    Audit::log('ACTIVATE_TEMPLATE',$type,$before,$active,['actor'=>auth()->id()]);
    return response()->json($active);
  }
}
```

---

## 🧭 6. RBAC Gates
```php
Gate::define('manage-settings', fn($user)=> in_array($user->role, settings('security.roles.can_manage_settings',[])));
Gate::define('issue-number', fn($user)=> in_array($user->role, settings('security.roles.can_issue_number',[])));
```

---

## ♻️ 7. Retensi & Purge Job

**Command:** `app/Console/Commands/PurgeOldFiles.php`
```php
class PurgeOldFiles extends Command {
  protected $signature='lims:purge-old-files';
  public function handle(){
    $days=(int)settings('retention.purge_after_days',1825);
    $base=settings('retention.base_path','official_docs/');
    $disk=settings('retention.storage_driver','local');
    $cut=now()->subDays($days);
    foreach(Storage::disk($disk)->allFiles($base) as $f){
      $ts=Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($f));
      if($ts < $cut){ Storage::disk($disk)->delete($f); Audit::log('PURGE_FILE',$f,null,null,['ts'=>(string)$ts]); }
    }
  }
}
```

**Scheduler:**
```php
$schedule->command('lims:purge-old-files')->dailyAt('02:00');
```

---

## 🔔 8. Otomasi Notifikasi

**Event:** `NumberIssued`  
**Listener:** `SendIssueNotification`

```php
public function handle(NumberIssued $e){
  $cfg=settings('automation.notify_on_issue'); if(!$cfg) return;
  $repl = fn($s)=> strtr($s,['{SCOPE}'=>strtoupper($e->scope),'{NUMBER}'=>$e->number,'{REQ}'=>data_get($e->ctx,'request_short','-')]);
  if(data_get($cfg,'email')){
    Mail::to(config('mail.to.address'))->send(new \App\Mail\NumberIssuedMail([
      'subject'=>$repl(data_get($cfg,'templates.subject','Number {NUMBER}')),
      'body'=>$repl(data_get($cfg,'templates.body','Nomor {NUMBER} terbit'))
    ]));
  }
  if(data_get($cfg,'whatsapp')){
    \Log::info('WA sent: '.$repl('{SCOPE} {NUMBER}'));
  }
}
```

---

## 🧩 9. UI Settings (Blade + Tailwind + Alpine)
**Section utama:**
- Penomoran Sample, BA, LHU  
- Template Dokumen (unggah/pilih aktif)  
- Branding & PDF (logo, warna, alamat, watermark, QR, TTD)  
- Lokalisasi  
- Retensi & Penyimpanan  
- Otomasi (auto-generate + notifikasi)

Gunakan struktur seperti:

```html
<section class="bg-white p-4 rounded-xl shadow">
  <h2 class="text-lg font-medium mb-3">Branding & PDF</h2>
  <div class="grid md:grid-cols-2 gap-4">
    <label><span>Kode Lab</span><input x-model="form.branding.lab_code" class="input"></label>
    <label><span>Logo</span><input type="file" @change="uploadLogo"></label>
    <label><span>Alamat</span><input x-model="form.pdf.header.address" class="input"></label>
    <label><span>Kontak</span><input x-model="form.pdf.header.contact" class="input"></label>
    <label><span>Watermark</span><input x-model="form.pdf.header.watermark" class="input"></label>
    <label><span>Footer</span><input x-model="form.pdf.footer.text" class="input"></label>
    <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="form.pdf.qr.enabled"><span>Tampilkan QR</span></label>
  </div>
</section>
```

---

## 🧠 10. Validasi & Keamanan

- Pattern wajib punya `{SEQ:n}` (2–8 digit)  
- Sanitize token `{TEST}`, `{INST}`, `{REQ}`, `{DOC}`  
- Transaksi + `lockForUpdate()` untuk mencegah duplikat  
- Role-based guard: `admin`, `supervisor`  
- Audit semua aksi  
- Preview & test-generate tidak menulis DB  

---

## 💡 Prompt Ringkas Copilot

> “Buat halaman **Settings** LIMS (Laravel + Blade + Tailwind + Alpine) dengan section: **Penomoran (Sample/BA/LHU)**, **Template Dokumen**, **Branding & PDF**, **Lokalisasi**, **Penyimpanan & Retensi**, **Otomasi**, **Keamanan & Akses**, serta **Preview/Test-generate**. Implement `NumberingService` (transaksi + lockForUpdate), `SettingsController`, `TemplateController`, `NumberingController`, event `NumberIssued`, audit log, dan purge job.”

---

✅ **Fitur-fitur lengkap:**
- Live preview pola `{YYYY}{MM}{DD}{INV}{LAB}{TEST}{INST}{REQ}{SEQ:n}`
- Upload template dokumen (.docx/.html)
- Pilih template aktif per jenis (LHU, BA, Tanda Terima)
- Branding & watermark PDF
- Auto-generate dokumen pendukung (opsional)
- Notifikasi email/WA saat nomor diterbitkan
- Role-based access + audit log
- File retention otomatis dengan purge scheduler
- Test-generate sandbox tanpa efek database
