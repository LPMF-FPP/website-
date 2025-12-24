# Panduan Integrasi Penomoran Terpusat

## Status Implementasi

✅ **Completed:**
- `NumberingService` sudah ada dan berfungsi di `app/Services/NumberingService.php`
- Endpoint API `GET /api/settings/numbering/current` sudah mengembalikan format yang benar
- Frontend `/settings` sudah diperbaiki untuk menampilkan nomor dengan benar (bukan `[object Object]`)
- Test `NumberingApiTest` sudah diperbarui dan lulus

## Pemetaan Nomor

| Halaman | Jenis Nomor | Scope | Source of Truth | Status Integrasi |
|---------|-------------|-------|-----------------|------------------|
| `/samples/test` | Kode Sampel | `sample_code` | `samples.sample_code` | ⚠️ Masih pakai `Sample::generateSampleCode()` |
| `/requests` | BA Penerimaan | `ba` | Settings/DB | ⚠️ Masih pakai `TestRequest::generateRequestNumber()` |
| `/delivery` | BA Penyerahan | `ba_penyerahan` | Settings/DB | ⚠️ Perlu diimplementasi |
| N/A (LHU) | Nomor LHU | `lhu` | Settings/DB | ⚠️ Perlu diimplementasi |
| `/tracking` | Nomor Resi | `tracking` | Settings/DB | ⚠️ Perlu diimplementasi |

## Cara Kerja NumberingService

### 1. Issue Number (Generate baru)

```php
use App\Services\NumberingService;

class SomeController {
    public function __construct(
        private readonly NumberingService $numbering
    ) {}
    
    public function store(Request $request) {
        // Generate nomor baru
        $sampleCode = $this->numbering->issue('sample_code', [
            'investigator_id' => $request->user()->id,
        ]);
        
        $sample = Sample::create([
            'sample_code' => $sampleCode,
            // ... other fields
        ]);
    }
}
```

### 2. Preview Next Number (tanpa mutasi)

```php
// Preview nomor berikutnya tanpa menyimpan
$nextNumber = $this->numbering->preview('sample_code');

// Preview dengan context
$nextBA = $this->numbering->preview('ba', [
    'investigator_id' => 7,
]);
```

### 3. Current Snapshot (untuk UI)

```php
// Mendapat nomor saat ini dan berikutnya
$snapshot = $this->numbering->currentSnapshot('sample_code');
// Returns: ['current' => 'SMP-2025-0128', 'next' => 'SMP-2025-0129', 'pattern' => '...']
```

## Refactoring Plan

### Fase 1: Refactor Sample Code Generation ✅ (Sudah ada service)

**File:** `app/Models/Sample.php`

**Sebelum:**
```php
protected static function generateSampleCode(): string
{
    // Logic manual dengan DB transaction
    return DB::transaction(function () use ($year, $romanMonth) {
        // ... complex logic
    });
}
```

**Sesudah:**
```php
use App\Services\NumberingService;

protected static function boot()
{
    parent::boot();
    
    static::creating(function ($model) {
        if (!$model->sample_code) {
            $numbering = app(NumberingService::class);
            $model->sample_code = $numbering->issue('sample_code');
        }
    });
}
```

### Fase 2: Refactor TestRequest Number Generation

**File:** `app/Models/TestRequest.php`

**Sebelum:**
```php
protected static function generateRequestNumber(): string
{
    // Manual logic
    return DB::transaction(function () use ($year) {
        // ...
    });
}
```

**Sesudah:**
```php
use App\Services\NumberingService;

protected static function boot()
{
    parent::boot();
    
    static::creating(function ($model) {
        if (!$model->request_number) {
            $numbering = app(NumberingService::class);
            $model->request_number = $numbering->issue('ba', [
                'investigator_id' => $model->investigator_id,
            ]);
        }
    });
}
```

### Fase 3: Implementasi BA Penyerahan

**File:** `app/Models/Delivery.php` atau controller yang menangani delivery

**Tambahkan kolom `ba_number` ke tabel `deliveries`:**
```php
// Migration
Schema::table('deliveries', function (Blueprint $table) {
    $table->string('ba_number')->nullable()->after('id');
});
```

**Generate nomor saat create delivery:**
```php
use App\Services\NumberingService;

class DeliveryController {
    public function __construct(
        private readonly NumberingService $numbering
    ) {}
    
    public function store(Request $request) {
        $baNumber = $this->numbering->issue('ba_penyerahan', [
            'request_short' => $request->request_number,
        ]);
        
        $delivery = Delivery::create([
            'ba_number' => $baNumber,
            'request_id' => $request->id,
            // ... other fields
        ]);
    }
}
```

### Fase 4: Implementasi Tracking Number

**File:** Controller atau model yang menangani tracking

**Tambahkan kolom `tracking_number` jika belum ada:**
```php
// Generate tracking number
$trackingNumber = $this->numbering->issue('tracking', [
    'request_short' => $request->request_number,
]);
```

## Konfigurasi Pattern

Pattern dikonfigurasi di `/settings` dan disimpan di `system_settings` table dengan key `numbering.<scope>`:

```php
// Contoh pattern yang tersimpan:
[
    'numbering' => [
        'sample_code' => [
            'pattern' => 'SMP-{YYYY}-{SEQ:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ],
        'ba' => [
            'pattern' => 'BA-Penerimaan-REQ-{YYYY}-{SEQ:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ],
        'ba_penyerahan' => [
            'pattern' => 'BA-Penyerahan-REQ-{YYYY}-{SEQ:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ],
        'lhu' => [
            'pattern' => 'LHU-{YYYY}-{MM}-{SEQ:5}',
            'reset' => 'monthly',
            'start_from' => 1,
        ],
        'tracking' => [
            'pattern' => 'RESI-{YYYY}{MM}{DD}/{SEQ:5}',
            'reset' => 'daily',
            'start_from' => 1,
        ],
    ]
]
```

## Placeholder yang Tersedia

| Placeholder | Deskripsi | Contoh |
|-------------|-----------|---------|
| `{LAB}` | Kode lab dari settings | `LPMF` |
| `{YYYY}` | Tahun 4 digit | `2025` |
| `{YY}` | Tahun 2 digit | `25` |
| `{MM}` | Bulan 2 digit | `12` |
| `{DD}` | Tanggal 2 digit | `19` |
| `{SEQ:n}` | Sequence number dengan n digit | `{SEQ:4}` → `0001` |
| `{INV}` | Investigator ID (dari context) | `07` |
| `{TEST}` | Test code (dari context) | `GCMS` |
| `{REQ}` | Request short code (dari context) | `REQ-25-0102` |
| `{DOC}` | Document code (dari context) | `LHU` |

## Frontend Integration (Sudah Selesai) ✅

File: `resources/js/pages/settings/index.js`

```javascript
async fetchCurrentNumbering() {
    this.state.currentNumberingLoading = true;
    try {
        const data = await this.apiFetch(this.api.numberingCurrent);
        
        // Helper to extract displayable value from response
        const extractValue = (value) => {
            if (!value) return '-';
            if (typeof value === 'string') return value;
            if (typeof value === 'object') {
                return value.current || value.next || '-';
            }
            return '-';
        };
        
        this.state.currentNumbering = {
            sample_code: extractValue(data.sample_code),
            ba: extractValue(data.ba),
            lhu: extractValue(data.lhu),
            ba_penyerahan: extractValue(data.ba_penyerahan),
            tracking: extractValue(data.tracking),
        };
    } catch (error) {
        this.setSectionError('numbering', error.message);
    } finally {
        this.state.currentNumberingLoading = false;
    }
}
```

## Testing

### Feature Test untuk Endpoint

File: `tests/Feature/Settings/NumberingApiTest.php` ✅

```php
public function test_numbering_current_returns_values(): void
{
    $user = User::factory()->create(['role' => 'admin']);
    
    $response = $this->actingAs($user)->getJson('/api/settings/numbering/current');
    
    $response->assertOk()
        ->assertJsonStructure([
            'sample_code' => ['current', 'next', 'pattern'],
            'ba' => ['current', 'next', 'pattern'],
            'lhu' => ['current', 'next', 'pattern'],
            'ba_penyerahan' => ['current', 'next', 'pattern'],
            'tracking' => ['current', 'next', 'pattern'],
        ]);
    
    // Verify values are strings or null
    $data = $response->json();
    foreach (['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'] as $scope) {
        $this->assertIsArray($data[$scope]);
        $this->assertArrayHasKey('current', $data[$scope]);
        $this->assertArrayHasKey('next', $data[$scope]);
        
        if ($data[$scope]['current'] !== null) {
            $this->assertIsString($data[$scope]['current']);
        }
        
        $this->assertIsString($data[$scope]['next']);
    }
}
```

### Unit Test untuk NumberingService

```php
public function test_issue_generates_sequential_numbers(): void
{
    $numbering = app(NumberingService::class);
    
    $first = $numbering->issue('sample_code');
    $second = $numbering->issue('sample_code');
    
    $this->assertNotEquals($first, $second);
    $this->assertStringContainsString('SMP', $first);
}
```

## Manual Testing Checklist

- [x] Buka `/settings` → section "Penomoran Saat Ini" menampilkan 5 nomor (bukan `[object Object]`)
- [x] Klik tombol "Refresh" → nomor di-update tanpa error
- [ ] Ubah pattern untuk `sample_code` di `/settings` → Save
- [ ] Generate sample baru di `/samples/test` → nomor mengikuti pattern baru
- [ ] Ubah reset period dari "yearly" ke "monthly" → nomor di-reset per bulan
- [ ] Generate BA Penerimaan → nomor sesuai pattern
- [ ] Generate BA Penyerahan → nomor sesuai pattern
- [ ] Generate tracking → nomor sesuai pattern

## Next Steps

1. ✅ Perbaiki frontend rendering (DONE)
2. ✅ Pastikan API endpoint stabil (DONE)
3. ✅ Buat/update feature test (DONE)
4. ⚠️ Refactor `Sample::generateSampleCode()` untuk pakai `NumberingService`
5. ⚠️ Refactor `TestRequest::generateRequestNumber()` untuk pakai `NumberingService`
6. ⚠️ Implementasi BA Penyerahan numbering
7. ⚠️ Implementasi Tracking numbering
8. ⚠️ Manual testing end-to-end

## Catatan Penting

- **Backward Compatibility**: Nomor yang sudah ada di database tidak akan berubah
- **Migration**: Tidak perlu migrasi data, sistem akan mulai generate nomor baru dengan pattern yang baru
- **Performance**: `NumberingService` menggunakan `lockForUpdate()` untuk mencegah race condition
- **Audit Trail**: Setiap issue number dicatat di audit log via `Audit::log('ISSUE_NUMBER', ...)`
- **Error Handling**: Jika config tidak ada, service mengembalikan default pattern untuk mencegah error 500

## Troubleshooting

### `[object Object]` masih muncul di UI
✅ **Fixed**: Frontend sudah diupdate untuk ekstrak `.current` atau `.next` dari response object

### 401 Unauthorized saat akses endpoint
- Pastikan user login dan punya role yang bisa akses settings (admin)
- Endpoint dilindungi `Gate::authorize('manage-settings')`

### Nomor tidak increment
- Cek apakah settings sudah di-seed dengan `SystemSettingSeeder`
- Cek tabel `sequences` apakah record ter-create
- Pastikan `start_from` di settings >= 1

### Pattern tidak berlaku
- Pastikan sudah Save settings di `/settings`
- Clear cache dengan `php artisan cache:clear`
- Pastikan kolom `pattern` tidak kosong di settings
