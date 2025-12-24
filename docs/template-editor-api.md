# Template Editor API - Dokumentasi & Curl Examples

**Tanggal:** 22 Desember 2025  
**Base URL:** `http://localhost:8000/api/templates`  
**Authentication:** Required (Laravel Sanctum/Session)  
**Authorization:** `manage-settings` gate

---

## 📋 Endpoints Overview

| Method | Endpoint | Description | Authorization |
|--------|----------|-------------|---------------|
| GET | `/api/templates` | List all templates | manage-settings |
| GET | `/api/templates/{id}` | Get single template | manage-settings |
| POST | `/api/templates` | Create new template | manage-settings |
| PUT | `/api/templates/{id}` | Update template | manage-settings |
| PUT | `/api/templates/{id}/issue` | Mark as issued | manage-settings |
| PUT | `/api/templates/{id}/activate` | Activate template | manage-settings |
| POST | `/api/templates/{id}/preview` | Preview as PDF | manage-settings |
| DELETE | `/api/templates/{id}` | Delete template | manage-settings |

---

## 🔐 Authentication Setup

```bash
# Login first to get session cookie
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "labmutufarmapol@gmail.com",
    "password": "your-password"
  }' \
  -c cookies.txt

# Use -b cookies.txt in subsequent requests
```

---

## 📝 1. Create New Template

### Request

```bash
curl -X POST http://localhost:8000/api/templates \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -b cookies.txt \
  -d '{
    "doc_type": "BA",
    "name": "Berita Acara Penerimaan v2",
    "code": "BA-PENERIMAAN-002",
    "version": 1,
    "status": "draft",
    "content_html": "<div><h1>BERITA ACARA PENERIMAAN BARANG BUKTI</h1><p>Nomor: {{request_number}}</p><p>Perkara: {{case_number}}</p><p>Penyidik: {{investigator_name}} ({{investigator_rank}})</p><p>NRP: {{investigator_nrp}}</p><p>Tersangka: {{suspect_name}}</p><p>Tanggal: {{date}}</p><p>Waktu: {{time}}</p><hr><h3>BARANG BUKTI:</h3><p>Jumlah: {{sample_count}} sampel</p><p>Deskripsi: {{sample_list}}</p><hr><p>Lokasi: {{location}}</p><p>Diterima oleh: {{received_by}}</p></div>",
    "content_css": "body { font-family: Arial, sans-serif; padding: 20px; } h1 { color: #1a1a1a; text-align: center; font-size: 18px; } h3 { color: #333; margin-top: 20px; } p { margin: 8px 0; } hr { margin: 15px 0; border: 1px solid #ddd; }",
    "gjs_components": {
      "type": "wrapper",
      "components": [
        {
          "type": "text",
          "content": "BERITA ACARA PENERIMAAN"
        }
      ]
    },
    "gjs_styles": [
      {
        "selectors": ["body"],
        "style": {"font-family": "Arial"}
      }
    ],
    "meta": {
      "description": "Template untuk Berita Acara Penerimaan Barang Bukti",
      "tags": ["ba", "penerimaan", "official"]
    }
  }'
```

### Response (201 Created)

```json
{
  "success": true,
  "message": "Template created successfully",
  "data": {
    "id": 3,
    "doc_type": "BA",
    "name": "Berita Acara Penerimaan v2",
    "code": "BA-PENERIMAAN-002",
    "version": 1,
    "status": "draft",
    "is_active": false,
    "content_html": "<div>...</div>",
    "content_css": "body { ... }",
    "gjs_components": {...},
    "gjs_styles": [...],
    "created_by": 11,
    "updated_by": 11,
    "created_at": "2025-12-22T05:55:00.000000Z",
    "updated_at": "2025-12-22T05:55:00.000000Z",
    "issued_at": null,
    "creator": {
      "id": 11,
      "name": "Admin User",
      "email": "labmutufarmapol@gmail.com"
    },
    "updater": {
      "id": 11,
      "name": "Admin User"
    }
  }
}
```

---

## 📄 2. List Templates

### Request

```bash
# List all templates
curl -X GET http://localhost:8000/api/templates \
  -H "Accept: application/json" \
  -b cookies.txt

# Filter by doc_type
curl -X GET "http://localhost:8000/api/templates?doc_type=BA" \
  -H "Accept: application/json" \
  -b cookies.txt

# Filter by status
curl -X GET "http://localhost:8000/api/templates?status=draft" \
  -H "Accept: application/json" \
  -b cookies.txt

# Only active templates
curl -X GET "http://localhost:8000/api/templates?active_only=1" \
  -H "Accept: application/json" \
  -b cookies.txt

# Combine filters
curl -X GET "http://localhost:8000/api/templates?doc_type=LHU&status=issued&active_only=1" \
  -H "Accept: application/json" \
  -b cookies.txt
```

### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "doc_type": "BA",
      "name": "Berita Acara Penerimaan v2",
      "status": "draft",
      "is_active": false,
      "created_at": "2025-12-22T05:55:00.000000Z",
      "creator": {...},
      "updater": {...}
    },
    {
      "id": 2,
      "doc_type": "BA",
      "name": "Berita Acara Penerimaan Sample",
      "status": "draft",
      "is_active": false,
      "created_at": "2025-12-22T05:50:00.000000Z",
      "creator": {...},
      "updater": {...}
    }
  ]
}
```

---

## 🔍 3. Get Single Template

### Request

```bash
curl -X GET http://localhost:8000/api/templates/2 \
  -H "Accept: application/json" \
  -b cookies.txt
```

### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 2,
    "doc_type": "BA",
    "name": "Berita Acara Penerimaan Sample",
    "code": "BA-PENERIMAAN-001",
    "version": 1,
    "status": "draft",
    "is_active": false,
    "content_html": "<div><h1>Berita Acara Penerimaan</h1>...</div>",
    "content_css": "body { font-family: Arial; }...",
    "gjs_components": {...},
    "gjs_styles": [...],
    "created_at": "2025-12-22T05:50:00.000000Z",
    "updated_at": "2025-12-22T05:50:00.000000Z",
    "issued_at": null,
    "creator": {...},
    "updater": {...}
  }
}
```

---

## ✏️ 4. Update Template

### Request

```bash
curl -X PUT http://localhost:8000/api/templates/2 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -b cookies.txt \
  -d '{
    "name": "Berita Acara Penerimaan (Updated)",
    "content_html": "<div><h1>BERITA ACARA PENERIMAAN BARANG BUKTI</h1><p>Nomor Permintaan: {{request_number}}</p><p>Nomor Perkara: {{case_number}}</p><p>Penyidik: {{investigator_name}}</p><p>Pangkat: {{investigator_rank}}</p><p>NRP: {{investigator_nrp}}</p><p>Kesatuan: {{investigator_jurisdiction}}</p><p>Tersangka: {{suspect_name}}</p><p>Tanggal Penerimaan: {{date}} Jam {{time}}</p><hr><h3>Barang Bukti yang Diterima:</h3><p>Jumlah sampel: {{sample_count}}</p><p>Detail: {{sample_list}}</p><p>Metode pengujian: {{test_methods}}</p><hr><p>Lokasi: {{location}}</p><p>Diterima oleh: {{received_by}}</p></div>",
    "content_css": "body { font-family: \"Times New Roman\", serif; padding: 30px; line-height: 1.6; } h1 { color: #000; text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; margin-bottom: 30px; } h3 { color: #000; font-size: 14px; margin-top: 20px; } p { margin: 10px 0; font-size: 12px; } hr { margin: 20px 0; border: 1px solid #000; }",
    "gjs_components": {
      "type": "wrapper",
      "components": [
        {
          "type": "text",
          "content": "Updated content"
        }
      ]
    }
  }'
```

### Response (200 OK)

```json
{
  "success": true,
  "message": "Template updated successfully",
  "data": {
    "id": 2,
    "name": "Berita Acara Penerimaan (Updated)",
    "content_html": "<div><h1>BERITA ACARA PENERIMAAN BARANG BUKTI</h1>...",
    "updated_at": "2025-12-22T06:00:00.000000Z"
  }
}
```

---

## 🎯 5. Activate Template

**Important:** Activating a template will automatically deactivate all other templates with the same `doc_type`.

### Request

```bash
curl -X PUT http://localhost:8000/api/templates/2/activate \
  -H "Accept: application/json" \
  -b cookies.txt
```

### Response (200 OK)

```json
{
  "success": true,
  "message": "Template activated successfully",
  "data": {
    "id": 2,
    "doc_type": "BA",
    "is_active": true,
    "updated_by": 11,
    "updated_at": "2025-12-22T06:01:00.000000Z"
  }
}
```

### Database Changes (Transaction)

```sql
-- Step 1: Deactivate all other BA templates
UPDATE document_templates 
SET is_active = false, updated_by = 11 
WHERE doc_type = 'BA' AND id != 2;

-- Step 2: Activate target template
UPDATE document_templates 
SET is_active = true, updated_by = 11 
WHERE id = 2;
```

---

## 📋 6. Mark Template as Issued

### Request

```bash
curl -X PUT http://localhost:8000/api/templates/2/issue \
  -H "Accept: application/json" \
  -b cookies.txt
```

### Response (200 OK)

```json
{
  "success": true,
  "message": "Template marked as issued",
  "data": {
    "id": 2,
    "status": "issued",
    "issued_at": "2025-12-22T06:02:00.000000Z",
    "updated_at": "2025-12-22T06:02:00.000000Z"
  }
}
```

### Error Response (400 Bad Request)

```json
{
  "success": false,
  "message": "Template is already issued"
}
```

---

## 📄 7. Preview Template as PDF

Renders the template with sample data and returns PDF inline.

### Request

```bash
curl -X POST http://localhost:8000/api/templates/2/preview \
  -H "Accept: application/pdf" \
  -b cookies.txt \
  --output preview.pdf

# View in browser (if running locally)
curl -X POST http://localhost:8000/api/templates/2/preview \
  -b cookies.txt \
  > preview.pdf && xdg-open preview.pdf
```

### Sample Data Used

#### For `doc_type = 'BA'`:

```json
{
  "request_number": "REQ-2025-0001",
  "case_number": "LP/001/I/2025/POLDA",
  "investigator_name": "AKP Budi Santoso, S.H.",
  "investigator_rank": "Ajun Komisaris Polisi",
  "investigator_nrp": "1234567890",
  "investigator_jurisdiction": "Polda Metro Jaya",
  "suspect_name": "Fulan bin Fulanah",
  "date": "22 December 2025",
  "time": "10:30",
  "sample_count": "3",
  "sample_list": "Sampel A (Bubuk Putih), Sampel B (Tablet), Sampel C (Cairan)",
  "test_methods": "Identifikasi, Konfirmasi GC-MS",
  "location": "Laboratorium Forensik Polda Metro Jaya",
  "received_by": "Dr. Analis Laboratorium"
}
```

#### For `doc_type = 'LHU'`:

```json
{
  "lhu_number": "LHU/001/I/2025",
  "request_number": "REQ-2025-0001",
  "case_number": "LP/001/I/2025/POLDA",
  "sample_code": "W001I2025",
  "sample_name": "Bubuk Putih",
  "test_date": "22 December 2025",
  "test_result": "Positif",
  "detected_substance": "Metamfetamina",
  "test_method": "GC-MS (Gas Chromatography-Mass Spectrometry)",
  "analyst_name": "Dr. Analis Utama, M.Si.",
  "analyst_nip": "198501012010011001",
  "lab_head_name": "Dr. Kepala Lab, M.Sc.",
  "lab_head_nip": "197001011995011001",
  "conclusion": "Barang bukti mengandung Metamfetamina (Golongan I)"
}
```

### Token Replacement Logic

```php
// Only replaces tokens in format: {{token}}
// Example HTML input:
<p>Nomor: {{request_number}}</p>
<p>Perkara: {{case_number}}</p>

// Output with sample data:
<p>Nomor: REQ-2025-0001</p>
<p>Perkara: LP/001/I/2025/POLDA</p>

// Unsupported tokens remain unchanged:
<p>Unknown: {{unknown_token}}</p>  // Stays as-is
```

### Response Headers

```
Content-Type: application/pdf
Content-Disposition: inline; filename="preview-BA-PENERIMAAN-001.pdf"
```

---

## 🗑️ 8. Delete Template

**Note:** Cannot delete active templates. Deactivate first.

### Request

```bash
curl -X DELETE http://localhost:8000/api/templates/3 \
  -H "Accept: application/json" \
  -b cookies.txt
```

### Response (200 OK)

```json
{
  "success": true,
  "message": "Template deleted successfully"
}
```

### Error Response (400 Bad Request)

```json
{
  "success": false,
  "message": "Cannot delete active template. Deactivate it first."
}
```

---

## 🔒 Authorization

All endpoints require the `manage-settings` gate. Users without this permission will receive:

### Response (403 Forbidden)

```json
{
  "message": "This action is unauthorized."
}
```

### Checking Authorization in Blade/Code

```php
@can('manage-settings')
  // Show template editor UI
@endcan

// In controller
Gate::authorize('manage-settings');
```

---

## 🧪 Testing Workflow

### Complete CRUD Test Sequence

```bash
# 1. Create template
TEMPLATE_ID=$(curl -s -X POST http://localhost:8000/api/templates \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"doc_type":"BA","name":"Test Template","code":"TEST-001","content_html":"<p>{{request_number}}</p>","content_css":"body{}"}' \
  | jq -r '.data.id')

echo "Created template ID: $TEMPLATE_ID"

# 2. List templates
curl -s http://localhost:8000/api/templates -b cookies.txt | jq '.data[] | {id, name, status, is_active}'

# 3. Update template
curl -X PUT http://localhost:8000/api/templates/$TEMPLATE_ID \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"name":"Test Template (Updated)"}' \
  | jq '.message'

# 4. Preview template
curl -X POST http://localhost:8000/api/templates/$TEMPLATE_ID/preview \
  -b cookies.txt \
  --output test-preview.pdf

echo "Preview saved to test-preview.pdf"

# 5. Mark as issued
curl -X PUT http://localhost:8000/api/templates/$TEMPLATE_ID/issue \
  -b cookies.txt \
  | jq '.data | {status, issued_at}'

# 6. Activate template
curl -X PUT http://localhost:8000/api/templates/$TEMPLATE_ID/activate \
  -b cookies.txt \
  | jq '.data | {is_active, updated_at}'

# 7. Verify activation (others should be inactive)
curl -s "http://localhost:8000/api/templates?doc_type=BA" \
  -b cookies.txt \
  | jq '.data[] | {id, name, is_active}'

# 8. Delete template (will fail if active)
curl -X DELETE http://localhost:8000/api/templates/$TEMPLATE_ID \
  -b cookies.txt \
  | jq '.'
```

---

## 📊 Database State After Activation

### Before Activation

| id | doc_type | name | is_active |
|----|----------|------|-----------|
| 1 | BA | Template A | true |
| 2 | BA | Template B | false |
| 3 | LHU | Template C | true |

### After Activating Template #2

```bash
curl -X PUT http://localhost:8000/api/templates/2/activate -b cookies.txt
```

| id | doc_type | name | is_active | Notes |
|----|----------|------|-----------|-------|
| 1 | BA | Template A | **false** | ✅ Auto-deactivated |
| 2 | BA | Template B | **true** | ✅ Activated |
| 3 | LHU | Template C | true | ⚠️ Unchanged (different doc_type) |

**Transaction ensures atomicity** - either all updates succeed or none.

---

## ⚠️ Error Handling

### Validation Errors (422 Unprocessable Entity)

```json
{
  "message": "The doc_type field is required. (and 2 more errors)",
  "errors": {
    "doc_type": ["The doc_type field is required."],
    "name": ["The name field is required."],
    "content_html": ["The content_html must be a string."]
  }
}
```

### Not Found (404)

```json
{
  "message": "No query results for model [App\\Models\\DocumentTemplate] 999"
}
```

### Server Error (500)

```json
{
  "success": false,
  "message": "Failed to activate template: Database connection lost"
}
```

---

## 📝 Notes

1. **Whitelist Tokens Only**: Template rendering uses strict `{{token}}` format. Other formats like `{token}`, `${token}`, or `[[token]]` will NOT be replaced.

2. **Transaction Safety**: The activate endpoint uses database transactions to ensure consistency. If any step fails, all changes are rolled back.

3. **Sample Data**: Preview endpoint uses hardcoded sample data based on `doc_type`. For production preview with real data, extend the controller.

4. **No Public Endpoints**: All template editor endpoints require authentication and `manage-settings` permission. Regular users cannot access.

5. **Soft Delete**: Templates use hard delete. Consider implementing soft deletes if you need audit trail.

---

## 🔗 Related Endpoints

These endpoints are **separate** from the template editor and remain unchanged:

- `/api/settings/document-templates/*` - Original settings UI endpoints
- `/api/settings/templates/*` - Legacy template system

The new `/api/templates/*` endpoints are specifically for the GrapesJS template editor interface.
