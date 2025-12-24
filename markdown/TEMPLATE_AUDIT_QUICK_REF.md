# Template Audit Logging - Quick Reference

## Summary
✅ Audit logging untuk semua operasi template high-impact  
✅ Semua endpoint memakai gate `manage-settings`  
✅ Before/after state tracking untuk semua perubahan

---

## File Changes

### 1. Modified: `app/Http/Controllers/Api/Settings/DocumentTemplateController.php`

**9 audit log additions:**

```diff
# 1. CREATE - store()
+ Audit::log(
+     action: 'TEMPLATE_CREATE',
+     before: null,
+     after: [template_id, name, doc_type, version, is_active],
+     context: [doc_type, format, render_engine]
+ );

# 2. UPLOAD - upload()
+ Audit::log(
+     action: 'TEMPLATE_UPLOAD',
+     before: null,
+     after: [template_id, name, version, storage_path],
+     context: [doc_type, format, original_filename, file_size]
+ );

# 3. UPDATE DRAFT - update()
+ Audit::log(
+     action: 'TEMPLATE_UPDATE_DRAFT',
+     before: [old template_id, version, checksum],
+     after: [new template_id, version, checksum],
+     context: [old/new template_ids, content_changed]
+ );

# 4. ISSUE/ACTIVATE - activate()
+ Get previous active template
+ Audit::log(
+     action: 'TEMPLATE_ISSUE_ACTIVATE',
+     before: [is_active=false, previous_active_template_id],
+     after: [is_active=true, version, name],
+     context: [doc_type, deactivated_template_id]
+ );

# 5. DEACTIVATE - deactivate()
+ Audit::log(
+     action: 'TEMPLATE_DEACTIVATE',
+     before: [is_active=true],
+     after: [is_active=false],
+     context: [doc_type]
+ );

# 6. PREVIEW HTML - previewTemplateHtml()
+ Audit::log(
+     action: 'TEMPLATE_PREVIEW',
+     context: [template_id, doc_type, format='html', preview_type]
+ );

# 7. PREVIEW PDF - previewTemplatePdf()
+ Audit::log(
+     action: 'TEMPLATE_PREVIEW',
+     context: [template_id, doc_type, format='pdf', preview_type]
+ );

# 8. PREVIEW GENERAL - preview()
+ Audit::log(
+     action: 'TEMPLATE_PREVIEW',
+     context: [doc_type, format, preview_type='general_preview']
+ );

# 9. DELETE - destroy()
+ Get template before deletion
+ Audit::log(
+     action: 'TEMPLATE_DELETE',
+     before: [template_id, name, version, is_active],
+     after: null,
+     context: [doc_type, was_active]
+ );
```

---

## Audit Log Actions

| Action | Operasi | Before | After | Context |
|--------|---------|--------|-------|---------|
| `TEMPLATE_CREATE` | Buat template baru | ❌ | ✅ | doc_type, format |
| `TEMPLATE_UPLOAD` | Upload file template | ❌ | ✅ | file_size, original_filename |
| `TEMPLATE_UPDATE_DRAFT` | Update draft/konten | ✅ | ✅ | content_changed, old/new IDs |
| `TEMPLATE_ISSUE_ACTIVATE` | Issue & aktivasi | ✅ | ✅ | previous active template |
| `TEMPLATE_DEACTIVATE` | Nonaktifkan | ✅ | ✅ | doc_type |
| `TEMPLATE_PREVIEW` | Preview HTML/PDF | ❌ | ❌ | format, preview_type |
| `TEMPLATE_DELETE` | Hapus template | ✅ | ❌ | was_active |

---

## Contoh Output Log

### 1. Create Template
```json
{
  "actor_id": 5,
  "action": "TEMPLATE_CREATE",
  "target": "template_123",
  "before": null,
  "after": {
    "template_id": 123,
    "name": "BA Penerimaan v3",
    "doc_type": "ba_penerimaan",
    "version": 1,
    "is_active": false
  },
  "context": {
    "doc_type": "ba_penerimaan",
    "format": "pdf",
    "render_engine": "browsershot"
  }
}
```

### 2. Update Draft
```json
{
  "actor_id": 5,
  "action": "TEMPLATE_UPDATE_DRAFT",
  "target": "template_123",
  "before": {
    "template_id": 123,
    "version": 1,
    "checksum": "a1b2c3"
  },
  "after": {
    "template_id": 124,
    "version": 2,
    "checksum": "f6e5d4"
  },
  "context": {
    "old_template_id": 123,
    "new_template_id": 124,
    "doc_type": "ba_penerimaan",
    "content_changed": true
  }
}
```

### 3. Issue/Activate
```json
{
  "actor_id": 5,
  "action": "TEMPLATE_ISSUE_ACTIVATE",
  "target": "template_124",
  "before": {
    "template_id": 124,
    "is_active": false,
    "previous_active_template_id": 120,
    "previous_active_template_name": "BA v2"
  },
  "after": {
    "template_id": 124,
    "is_active": true,
    "version": 2
  },
  "context": {
    "doc_type": "ba_penerimaan",
    "deactivated_template_id": 120
  }
}
```

### 4. Preview
```json
{
  "actor_id": 5,
  "action": "TEMPLATE_PREVIEW",
  "target": "template_124",
  "before": null,
  "after": null,
  "context": {
    "template_id": 124,
    "doc_type": "ba_penerimaan",
    "format": "pdf",
    "preview_type": "template_pdf"
  }
}
```

### 5. Delete
```json
{
  "actor_id": 5,
  "action": "TEMPLATE_DELETE",
  "target": "template_120",
  "before": {
    "template_id": 120,
    "name": "BA v2",
    "doc_type": "ba_penerimaan",
    "version": 1,
    "is_active": false
  },
  "after": null,
  "context": {
    "doc_type": "ba_penerimaan",
    "was_active": false
  }
}
```

---

## Query Examples

### Get semua operasi template hari ini
```sql
SELECT * FROM audit_logs 
WHERE action LIKE 'TEMPLATE_%' 
  AND DATE(created_at) = CURRENT_DATE
ORDER BY created_at DESC;
```

### Get aktivitas template tertentu
```sql
SELECT * FROM audit_logs 
WHERE target = 'template_124' 
   OR context->>'template_id' = '124'
ORDER BY created_at DESC;
```

### Get aktivasi template (issue)
```sql
SELECT 
    u.name,
    al.context->>'doc_type' as doc_type,
    al.after->>'name' as template_name,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON u.id = al.actor_id
WHERE al.action = 'TEMPLATE_ISSUE_ACTIVATE'
ORDER BY al.created_at DESC;
```

### Get penghapusan template aktif
```sql
SELECT * FROM audit_logs
WHERE action = 'TEMPLATE_DELETE'
  AND before->>'is_active' = 'true';
```

---

## Gate Authorization

Semua 13 endpoint memakai `Gate::authorize('manage-settings')`:

```php
// Semua method di DocumentTemplateController
public function index() { Gate::authorize('manage-settings'); ... }
public function byType() { Gate::authorize('manage-settings'); ... }
public function show() { Gate::authorize('manage-settings'); ... }
public function store() { Gate::authorize('manage-settings'); ... }
public function update() { Gate::authorize('manage-settings'); ... }
public function upload() { Gate::authorize('manage-settings'); ... }
public function activate() { Gate::authorize('manage-settings'); ... }
public function deactivate() { Gate::authorize('manage-settings'); ... }
public function preview() { Gate::authorize('manage-settings'); ... }
public function previewTemplateHtml() { Gate::authorize('manage-settings'); ... }
public function previewTemplatePdf() { Gate::authorize('manage-settings'); ... }
public function updateContent() { Gate::authorize('manage-settings'); ... }
public function destroy() { Gate::authorize('manage-settings'); ... }
```

**Response jika unauthorized:**
```json
{
  "success": false,
  "message": "Unauthorized access"
}
```
HTTP 403 Forbidden

---

## Testing

### 1. Manual Test - Create & Activate
```bash
# 1. Login sebagai user dengan permission manage-settings
# 2. Buat template baru via GrapesJS editor
# 3. Check audit log:
SELECT * FROM audit_logs WHERE action = 'TEMPLATE_CREATE' ORDER BY id DESC LIMIT 1;

# 4. Activate template
# 5. Check audit log:
SELECT * FROM audit_logs WHERE action = 'TEMPLATE_ISSUE_ACTIVATE' ORDER BY id DESC LIMIT 1;
```

### 2. Verify Before/After State
```php
// Create template
$log = AuditLog::where('action', 'TEMPLATE_CREATE')->latest()->first();
// Verify: before = null, after has template_id, name, doc_type

// Update template
$log = AuditLog::where('action', 'TEMPLATE_UPDATE_DRAFT')->latest()->first();
// Verify: before has old checksum, after has new checksum
// Verify: context->content_changed = true if content changed

// Activate template
$log = AuditLog::where('action', 'TEMPLATE_ISSUE_ACTIVATE')->latest()->first();
// Verify: before->previous_active_template_id exists
// Verify: after->is_active = true
```

### 3. Verify Gate Check
```bash
# Test tanpa permission
curl -X POST /api/settings/document-templates \
  -H "Authorization: Bearer <token_user_tanpa_permission>" \
  -H "Accept: application/json"

# Expected response:
# {"success": false, "message": "Unauthorized access"}
# HTTP 403
```

---

## Monitoring Queries

### Daily Activity Summary
```sql
SELECT 
    DATE(created_at) as date,
    COUNT(CASE WHEN action = 'TEMPLATE_CREATE' THEN 1 END) as creates,
    COUNT(CASE WHEN action = 'TEMPLATE_UPDATE_DRAFT' THEN 1 END) as updates,
    COUNT(CASE WHEN action = 'TEMPLATE_ISSUE_ACTIVATE' THEN 1 END) as activations,
    COUNT(CASE WHEN action = 'TEMPLATE_DELETE' THEN 1 END) as deletions
FROM audit_logs
WHERE action LIKE 'TEMPLATE_%'
  AND created_at >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

### User Activity Ranking
```sql
SELECT 
    u.name,
    COUNT(*) as total_operations,
    COUNT(CASE WHEN al.action = 'TEMPLATE_ISSUE_ACTIVATE' THEN 1 END) as activations
FROM users u
JOIN audit_logs al ON al.actor_id = u.id
WHERE al.action LIKE 'TEMPLATE_%'
  AND al.created_at >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY u.id, u.name
ORDER BY total_operations DESC
LIMIT 10;
```

---

## Status

✅ **Complete**
- 9 operasi audit log ditambahkan
- Semua endpoint punya gate check
- Before/after state tracking lengkap
- Documentation dengan contoh output

**File:** 1 modified  
**Lines changed:** ~150 lines (audit log additions)
