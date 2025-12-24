# Sistem Manajemen Dokumen Penyidik

Dokumentasi lengkap untuk sistem manajemen dokumen berbasis penyidik.

## Struktur Folder

Semua dokumen disimpan di:
```
storage/app/public/investigators/{folder_key}/[REQ-NUMBER]/(uploads|generated)/{type}/
```

Contoh:
```
storage/app/public/investigators/97010966-gifari-muhammad-syaba/
├── REQ-2025-0001/
│   ├── uploads/
│   │   ├── request_letter/
│   │   │   └── uuid.pdf
│   │   └── sample_photo/
│   │       └── uuid.jpg
│   └── generated/
│       ├── lhu/
│       │   └── uuid.html
│       └── ba_penyerahan/
│           └── uuid.html
└── general/
    └── uploads/
        └── other/
            └── uuid.pdf
```

## Database Schema

### Tabel `investigators`
- **folder_key** (string, unique): NRP-slug-nama (contoh: `97010966-gifari-muhammad-syaba`)

### Tabel `documents`
- **investigator_id**: Foreign key ke investigators
- **test_request_id**: Foreign key ke test_requests (nullable untuk dokumen umum)
- **document_type**: Jenis dokumen (request_letter, lhu, ba_penyerahan, dll)
- **source**: upload | generated
- **filename**: Nama file asli
- **original_filename**: Nama file saat upload
- **file_path**: Path relatif dari storage disk
- **path**: Alias untuk file_path
- **mime_type**: MIME type file
- **file_size**: Ukuran file dalam bytes
- **extra**: JSON untuk metadata tambahan

## Service: DocumentService

### Methods

#### storeUpload()
```php
$documentService->storeUpload(
    file: $uploadedFile,
    investigator: $investigator,
    testRequest: $testRequest, // nullable
    type: 'request_letter',
    extra: ['description' => 'Surat permintaan original']
);
```

#### storeGenerated()
```php
$documentService->storeGenerated(
    sourcePath: '/path/to/generated/file.html',
    investigator: $investigator,
    testRequest: $testRequest,
    type: 'lhu',
    extra: ['report_number' => 'FLHU001']
);
```

#### getDocuments()
```php
$documents = $documentService->getDocuments(
    investigator: $investigator,
    filters: [
        'type' => 'lhu',
        'source' => 'generated',
        'request_id' => 1
    ]
);
```

#### delete()
```php
$documentService->delete($document);
```

## Routes

### List Documents
```
GET /investigators/{investigator}/documents
```

### Upload Form
```
GET /investigators/{investigator}/documents/create
```

### Store Upload
```
POST /investigators/{investigator}/documents
```

### View Document Details
```
GET /documents/{document}
```

### Download Document
```
GET /documents/{document}/download
```

### Delete Document
```
DELETE /documents/{document}
```

## Authorization (Policy)

### Permissions
- **Admin**: Full access (view, upload, download, delete all)
- **Analyst**: View, upload, download, delete uploads only
- **Others**: No access by default

### Policy Methods
- `viewDocuments(User, Investigator)`: Akses list dokumen
- `view(User, Document)`: Lihat detail dokumen
- `uploadDocument(User, Investigator)`: Upload dokumen baru
- `download(User, Document)`: Download dokumen
- `delete(User, Document)`: Hapus dokumen

## Validasi

### File Upload
- **Max Size**: 20MB
- **Allowed MIME Types**:
  - Images: jpeg, png, gif, webp
  - Documents: pdf, doc, docx, xls, xlsx
  - Text: html, txt

### Transaction Safety
Semua operasi write (upload, delete) menggunakan database transaction untuk memastikan konsistensi data.

## Usage Example

### 1. Upload Document
```php
use App\Services\DocumentService;
use Illuminate\Http\Request;

public function store(Request $request, Investigator $investigator)
{
    $documentService = app(DocumentService::class);
    
    $validated = $request->validate([
        'file' => 'required|file|max:20480|mimes:jpg,jpeg,png,pdf,doc,docx',
        'test_request_id' => 'nullable|exists:test_requests,id',
        'document_type' => 'required|string',
    ]);
    
    $testRequest = $validated['test_request_id'] 
        ? TestRequest::find($validated['test_request_id'])
        : null;
    
    $document = $documentService->storeUpload(
        $request->file('file'),
        $investigator,
        $testRequest,
        $validated['document_type']
    );
    
    return redirect()->route('investigator.documents.index', $investigator);
}
```

### 2. Store Generated Document
```php
// After generating LHU
$generatedFilePath = base_path('output/laporan-hasil-uji/Laporan_Hasil_Uji_FLHU001.html');

$document = $documentService->storeGenerated(
    $generatedFilePath,
    $testRequest->investigator,
    $testRequest,
    'lhu',
    ['report_number' => 'FLHU001']
);
```

### 3. List Documents
```php
$investigator = Investigator::find(1);

$documents = $documentService->getDocuments($investigator, [
    'type' => 'lhu',
    'source' => 'generated'
]);

foreach ($documents as $doc) {
    echo $doc->filename . ' - ' . $doc->file_size . ' bytes' . PHP_EOL;
}
```

### 4. Download Document
```php
public function download(Document $document)
{
    Gate::authorize('download', $document);
    
    $documentService = app(DocumentService::class);
    
    if (!$documentService->fileExists($document)) {
        abort(404);
    }
    
    $filePath = $documentService->getFilePath($document);
    return response()->download($filePath, $document->filename);
}
```

## Testing

### Check Folder Key Generation
```bash
php artisan tinker

>>> $inv = App\Models\Investigator::first();
>>> $inv->folder_key
=> "97010966-gifari-muhammad-syaba"

>>> $inv->getDocumentPath('REQ-2025-0001', 'uploads', 'request_letter')
=> "investigators/97010966-gifari-muhammad-syaba/REQ-2025-0001/uploads/request_letter"
```

### Test Upload
```bash
# Create test file
$file = Illuminate\Http\UploadedFile::fake()->image('test.jpg');

# Upload
$service = app(App\Services\DocumentService::class);
$doc = $service->storeUpload(
    $file,
    App\Models\Investigator::first(),
    null,
    'test_photo'
);

echo $doc->file_path;
```

## Migration Commands

```bash
# Run migrations
php artisan migrate

# Rollback if needed
php artisan migrate:rollback --step=2

# Fresh migration (WARNING: deletes data)
php artisan migrate:fresh
```

## Storage Link

Pastikan symbolic link sudah dibuat:
```bash
php artisan storage:link
```

Akses file via browser:
```
http://127.0.0.1:8000/storage/investigators/97010966-gifari-muhammad-syaba/...
```

## Security Considerations

1. **Authorization**: Semua route dilindungi policy
2. **Signed URLs**: Download menggunakan signed URLs
3. **MIME Type Validation**: Hanya tipe file tertentu diizinkan
4. **File Size Limits**: Max 20MB per file
5. **Transaction Safety**: Database consistency dijaga
6. **Storage Isolation**: Setiap penyidik punya folder terpisah

## Troubleshooting

### Folder Key Null
Jalankan migration ulang atau update manual:
```php
Investigator::all()->each(function ($inv) {
    $inv->folder_key = Investigator::generateFolderKey($inv);
    $inv->save();
});
```

### File Not Found
Cek apakah file ada di storage:
```bash
ls -la storage/app/public/investigators/
```

### Permission Denied
Pastikan folder writable:
```bash
chmod -R 775 storage/app/public/investigators/
```
