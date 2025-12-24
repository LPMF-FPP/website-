# Template Audit Logging Implementation

## Summary
Added comprehensive audit logging for all high-impact template operations with `manage-settings` gate checks.

**Changed Files:** 1  
**Status:** ✅ Complete - All operations logged with before/after state

---

## Changes Made

### 1. ✅ Gate Authorization
All 13 endpoints in `DocumentTemplateController` use `Gate::authorize('manage-settings')`:
- ✅ `index()` - List templates
- ✅ `byType()` - Templates by type
- ✅ `show()` - View template
- ✅ `store()` - Create template
- ✅ `update()` - Update draft
- ✅ `upload()` - Upload file
- ✅ `activate()` - Issue/activate template
- ✅ `deactivate()` - Deactivate template
- ✅ `preview()` - Preview with sample data
- ✅ `previewTemplateHtml()` - HTML preview
- ✅ `previewTemplatePdf()` - PDF preview
- ✅ `updateContent()` - Update content
- ✅ `destroy()` - Delete template

### 2. ✅ Audit Logging Operations

All high-impact operations now log to `audit_logs` table via `Audit::log()`:

| Operation | Action Name | Before State | After State |
|-----------|-------------|--------------|-------------|
| **Create** | `TEMPLATE_CREATE` | `null` | template_id, name, doc_type, version, is_active |
| **Upload** | `TEMPLATE_UPLOAD` | `null` | template_id, name, version, storage_path |
| **Update Draft** | `TEMPLATE_UPDATE_DRAFT` | old template_id, name, version, checksum | new template_id, name, version, checksum |
| **Issue/Activate** | `TEMPLATE_ISSUE_ACTIVATE` | is_active=false, previous active template | is_active=true, version, name |
| **Deactivate** | `TEMPLATE_DEACTIVATE` | is_active=true | is_active=false |
| **Preview** | `TEMPLATE_PREVIEW` | `null` | `null` (context only) |
| **Delete** | `TEMPLATE_DELETE` | template_id, name, version, is_active | `null` |

### 3. ✅ Audit Log Schema

Uses existing `audit_logs` table and `AuditLog` model:

```php
protected $fillable = [
    'actor_id',    // User who performed action
    'action',      // Action name (e.g., TEMPLATE_CREATE)
    'target',      // Template code or "template_{id}"
    'before',      // JSON: state before action
    'after',       // JSON: state after action
    'context',     // JSON: additional metadata
];
```

---

## File Changes

### Modified: `app/Http/Controllers/Api/Settings/DocumentTemplateController.php`

**Summary:** Added audit logging for all 7 high-impact operations.

#### Change 1: Create Template
```php
// After creating template
Audit::log(
    action: 'TEMPLATE_CREATE',
    target: $template->code ?? "template_{$template->id}",
    before: null,
    after: [
        'template_id' => $template->id,
        'name' => $template->name,
        'doc_type' => $template->type->value,
        'version' => $template->version,
        'is_active' => $template->is_active,
    ],
    context: [
        'template_id' => $template->id,
        'doc_type' => $template->type->value,
        'format' => $template->format->value,
        'render_engine' => $template->render_engine?->value,
    ]
);
```

#### Change 2: Update Draft
```php
// After creating new version
Audit::log(
    action: 'TEMPLATE_UPDATE_DRAFT',
    target: $template->code ?? "template_{$template->id}",
    before: [
        'template_id' => $template->id,
        'name' => $template->name,
        'version' => $template->version,
        'checksum' => $template->checksum,
        'is_active' => $template->is_active,
    ],
    after: [
        'template_id' => $newTemplate->id,
        'name' => $newTemplate->name,
        'version' => $newTemplate->version,
        'checksum' => $newTemplate->checksum,
        'is_active' => $newTemplate->is_active,
    ],
    context: [
        'old_template_id' => $template->id,
        'new_template_id' => $newTemplate->id,
        'doc_type' => $template->type->value,
        'content_changed' => $template->checksum !== $newTemplate->checksum,
    ]
);
```

#### Change 3: Issue/Activate Template
```php
// Before activation
$oldTemplate = DocumentTemplate::find($templateId);
$previousActiveTemplate = DocumentTemplate::where('type', $oldTemplate->type)
    ->where('is_active', true)
    ->where('id', '!=', $templateId)
    ->first();

// After activation
Audit::log(
    action: 'TEMPLATE_ISSUE_ACTIVATE',
    target: $template->code ?? "template_{$template->id}",
    before: [
        'template_id' => $oldTemplate->id,
        'is_active' => $oldTemplate->is_active,
        'previous_active_template_id' => $previousActiveTemplate?->id,
        'previous_active_template_name' => $previousActiveTemplate?->name,
    ],
    after: [
        'template_id' => $template->id,
        'is_active' => $template->is_active,
        'version' => $template->version,
        'name' => $template->name,
    ],
    context: [
        'template_id' => $template->id,
        'doc_type' => $template->type->value,
        'format' => $template->format->value,
        'deactivated_template_id' => $previousActiveTemplate?->id,
    ]
);
```

#### Change 4: Deactivate Template
```php
$oldTemplate = DocumentTemplate::find($templateId);
$template = $this->repository->deactivateTemplate($templateId);

Audit::log(
    action: 'TEMPLATE_DEACTIVATE',
    target: $template->code ?? "template_{$template->id}",
    before: [
        'template_id' => $oldTemplate->id,
        'is_active' => $oldTemplate->is_active,
    ],
    after: [
        'template_id' => $template->id,
        'is_active' => $template->is_active,
    ],
    context: [
        'template_id' => $template->id,
        'doc_type' => $template->type->value,
    ]
);
```

#### Change 5: Preview Operations (3 endpoints)
```php
// HTML Preview
Audit::log(
    action: 'TEMPLATE_PREVIEW',
    target: $template->code ?? "template_{$template->id}",
    before: null,
    after: null,
    context: [
        'template_id' => $template->id,
        'doc_type' => $template->type->value,
        'format' => 'html',
        'preview_type' => 'template_html',
    ]
);

// PDF Preview
Audit::log(
    action: 'TEMPLATE_PREVIEW',
    target: $template->code ?? "template_{$template->id}",
    before: null,
    after: null,
    context: [
        'template_id' => $template->id,
        'doc_type' => $template->type->value,
        'format' => 'pdf',
        'preview_type' => 'template_pdf',
    ]
);

// General Preview (by type/format)
Audit::log(
    action: 'TEMPLATE_PREVIEW',
    target: "preview_{$type}_{$format}",
    before: null,
    after: null,
    context: [
        'doc_type' => $type,
        'format' => $format,
        'preview_type' => 'general_preview',
    ]
);
```

#### Change 6: Upload Template
```php
Audit::log(
    action: 'TEMPLATE_UPLOAD',
    target: $template->code ?? "template_{$template->id}",
    before: null,
    after: [
        'template_id' => $template->id,
        'name' => $template->name,
        'version' => $template->version,
        'storage_path' => $template->storage_path,
    ],
    context: [
        'template_id' => $template->id,
        'doc_type' => $docType->value,
        'format' => $format->value,
        'original_filename' => $template->meta['original_filename'] ?? null,
        'file_size' => $request->file('file')->getSize(),
    ]
);
```

#### Change 7: Delete Template
```php
// Get template before deletion
$template = DocumentTemplate::find($templateId);

Audit::log(
    action: 'TEMPLATE_DELETE',
    target: $template->code ?? "template_{$templateId}",
    before: [
        'template_id' => $template->id,
        'name' => $template->name,
        'doc_type' => $template->type->value,
        'version' => $template->version,
        'is_active' => $template->is_active,
    ],
    after: null,
    context: [
        'template_id' => $templateId,
        'doc_type' => $template->type->value,
        'was_active' => $template->is_active,
    ]
);

$this->repository->deleteTemplate($templateId);
```

---

## Example Audit Log Output

### 1. Create Template
```json
{
  "id": 101,
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
    "template_id": 123,
    "doc_type": "ba_penerimaan",
    "format": "pdf",
    "render_engine": "browsershot"
  },
  "created_at": "2025-12-22T10:30:45.000000Z"
}
```

### 2. Update Draft
```json
{
  "id": 102,
  "actor_id": 5,
  "action": "TEMPLATE_UPDATE_DRAFT",
  "target": "template_123",
  "before": {
    "template_id": 123,
    "name": "BA Penerimaan v3",
    "version": 1,
    "checksum": "a1b2c3d4e5f6",
    "is_active": false
  },
  "after": {
    "template_id": 124,
    "name": "BA Penerimaan v3 (Updated)",
    "version": 2,
    "checksum": "f6e5d4c3b2a1",
    "is_active": false
  },
  "context": {
    "old_template_id": 123,
    "new_template_id": 124,
    "doc_type": "ba_penerimaan",
    "content_changed": true
  },
  "created_at": "2025-12-22T11:15:22.000000Z"
}
```

### 3. Issue/Activate Template
```json
{
  "id": 103,
  "actor_id": 5,
  "action": "TEMPLATE_ISSUE_ACTIVATE",
  "target": "template_124",
  "before": {
    "template_id": 124,
    "is_active": false,
    "previous_active_template_id": 120,
    "previous_active_template_name": "BA Penerimaan v2"
  },
  "after": {
    "template_id": 124,
    "is_active": true,
    "version": 2,
    "name": "BA Penerimaan v3 (Updated)"
  },
  "context": {
    "template_id": 124,
    "doc_type": "ba_penerimaan",
    "format": "pdf",
    "deactivated_template_id": 120
  },
  "created_at": "2025-12-22T11:20:00.000000Z"
}
```

### 4. Deactivate Template
```json
{
  "id": 104,
  "actor_id": 5,
  "action": "TEMPLATE_DEACTIVATE",
  "target": "template_124",
  "before": {
    "template_id": 124,
    "is_active": true
  },
  "after": {
    "template_id": 124,
    "is_active": false
  },
  "context": {
    "template_id": 124,
    "doc_type": "ba_penerimaan"
  },
  "created_at": "2025-12-22T14:30:10.000000Z"
}
```

### 5. Preview Template (HTML)
```json
{
  "id": 105,
  "actor_id": 5,
  "action": "TEMPLATE_PREVIEW",
  "target": "template_124",
  "before": null,
  "after": null,
  "context": {
    "template_id": 124,
    "doc_type": "ba_penerimaan",
    "format": "html",
    "preview_type": "template_html"
  },
  "created_at": "2025-12-22T11:18:45.000000Z"
}
```

### 6. Preview Template (PDF)
```json
{
  "id": 106,
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
  },
  "created_at": "2025-12-22T11:19:02.000000Z"
}
```

### 7. Upload Template
```json
{
  "id": 107,
  "actor_id": 5,
  "action": "TEMPLATE_UPLOAD",
  "target": "template_125",
  "before": null,
  "after": {
    "template_id": 125,
    "name": "LHU Custom Template",
    "version": 1,
    "storage_path": "templates/lhu/pdf/template_125.pdf"
  },
  "context": {
    "template_id": 125,
    "doc_type": "lhu",
    "format": "pdf",
    "original_filename": "lhu_custom.pdf",
    "file_size": 245678
  },
  "created_at": "2025-12-22T09:45:30.000000Z"
}
```

### 8. Delete Template
```json
{
  "id": 108,
  "actor_id": 5,
  "action": "TEMPLATE_DELETE",
  "target": "template_120",
  "before": {
    "template_id": 120,
    "name": "BA Penerimaan v2",
    "doc_type": "ba_penerimaan",
    "version": 1,
    "is_active": false
  },
  "after": null,
  "context": {
    "template_id": 120,
    "doc_type": "ba_penerimaan",
    "was_active": false
  },
  "created_at": "2025-12-22T15:00:00.000000Z"
}
```

---

## Querying Audit Logs

### 1. Get All Template Operations
```php
$logs = AuditLog::where('action', 'LIKE', 'TEMPLATE_%')
    ->with('actor:id,name,email')
    ->orderBy('created_at', 'desc')
    ->get();
```

### 2. Get Operations for Specific Template
```php
$logs = AuditLog::where('target', 'template_124')
    ->orWhereJsonContains('context->template_id', 124)
    ->orderBy('created_at', 'desc')
    ->get();
```

### 3. Get Operations by User
```php
$logs = AuditLog::where('actor_id', 5)
    ->where('action', 'LIKE', 'TEMPLATE_%')
    ->orderBy('created_at', 'desc')
    ->get();
```

### 4. Get Activations Only
```php
$activations = AuditLog::where('action', 'TEMPLATE_ISSUE_ACTIVATE')
    ->orderBy('created_at', 'desc')
    ->get();
```

### 5. Get Preview Operations
```php
$previews = AuditLog::where('action', 'TEMPLATE_PREVIEW')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

### 6. Get Deletions of Active Templates
```php
$deletions = AuditLog::where('action', 'TEMPLATE_DELETE')
    ->whereJsonPath('before.is_active', true)
    ->get();
```

### 7. Get Template Activity by Doc Type
```php
$baLogs = AuditLog::where('action', 'LIKE', 'TEMPLATE_%')
    ->whereJsonContains('context->doc_type', 'ba_penerimaan')
    ->orderBy('created_at', 'desc')
    ->get();
```

### 8. Get Content Changes (version updates)
```php
$updates = AuditLog::where('action', 'TEMPLATE_UPDATE_DRAFT')
    ->whereJsonPath('context.content_changed', true)
    ->get();
```

---

## SQL Examples

### 1. Recent Template Activity
```sql
SELECT 
    al.id,
    u.name as actor_name,
    al.action,
    al.target,
    al.context->>'doc_type' as doc_type,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON u.id = al.actor_id
WHERE al.action LIKE 'TEMPLATE_%'
ORDER BY al.created_at DESC
LIMIT 20;
```

### 2. Template Activation History
```sql
SELECT 
    al.created_at,
    u.name as actor_name,
    al.before->>'previous_active_template_id' as old_template,
    al.after->>'template_id' as new_template,
    al.context->>'doc_type' as doc_type
FROM audit_logs al
LEFT JOIN users u ON u.id = al.actor_id
WHERE al.action = 'TEMPLATE_ISSUE_ACTIVATE'
ORDER BY al.created_at DESC;
```

### 3. User Activity Summary
```sql
SELECT 
    u.name,
    COUNT(CASE WHEN al.action = 'TEMPLATE_CREATE' THEN 1 END) as creates,
    COUNT(CASE WHEN al.action = 'TEMPLATE_UPDATE_DRAFT' THEN 1 END) as updates,
    COUNT(CASE WHEN al.action = 'TEMPLATE_ISSUE_ACTIVATE' THEN 1 END) as activations,
    COUNT(CASE WHEN al.action = 'TEMPLATE_DELETE' THEN 1 END) as deletions,
    COUNT(CASE WHEN al.action = 'TEMPLATE_PREVIEW' THEN 1 END) as previews
FROM users u
LEFT JOIN audit_logs al ON al.actor_id = u.id AND al.action LIKE 'TEMPLATE_%'
GROUP BY u.id, u.name
ORDER BY creates + updates + activations DESC;
```

### 4. Templates Activated Then Deactivated
```sql
WITH activations AS (
    SELECT 
        context->>'template_id' as template_id,
        created_at as activated_at
    FROM audit_logs
    WHERE action = 'TEMPLATE_ISSUE_ACTIVATE'
),
deactivations AS (
    SELECT 
        context->>'template_id' as template_id,
        created_at as deactivated_at
    FROM audit_logs
    WHERE action = 'TEMPLATE_DEACTIVATE'
)
SELECT 
    a.template_id,
    a.activated_at,
    d.deactivated_at,
    EXTRACT(EPOCH FROM (d.deactivated_at - a.activated_at))/3600 as hours_active
FROM activations a
JOIN deactivations d ON a.template_id = d.template_id
WHERE d.deactivated_at > a.activated_at
ORDER BY a.activated_at DESC;
```

---

## Monitoring & Alerting

### 1. Critical Operations Alert
Monitor for:
- ✅ Active template deletions
- ✅ Activation of templates without preview
- ✅ Multiple activations in short time

```php
// Alert if active template deleted
$criticalDeletions = AuditLog::where('action', 'TEMPLATE_DELETE')
    ->whereJsonPath('before.is_active', true)
    ->where('created_at', '>=', now()->subHour())
    ->get();

if ($criticalDeletions->isNotEmpty()) {
    // Send alert to admins
}
```

### 2. Audit Trail Report
```php
// Daily summary report
$summary = [
    'created' => AuditLog::where('action', 'TEMPLATE_CREATE')
        ->whereDate('created_at', today())->count(),
    'updated' => AuditLog::where('action', 'TEMPLATE_UPDATE_DRAFT')
        ->whereDate('created_at', today())->count(),
    'activated' => AuditLog::where('action', 'TEMPLATE_ISSUE_ACTIVATE')
        ->whereDate('created_at', today())->count(),
    'deleted' => AuditLog::where('action', 'TEMPLATE_DELETE')
        ->whereDate('created_at', today())->count(),
];
```

---

## Verification Checklist

- [x] **All endpoints have `manage-settings` gate**
  - [x] index() ✓
  - [x] byType() ✓
  - [x] show() ✓
  - [x] store() ✓
  - [x] update() ✓
  - [x] upload() ✓
  - [x] activate() ✓
  - [x] deactivate() ✓
  - [x] preview() ✓
  - [x] previewTemplateHtml() ✓
  - [x] previewTemplatePdf() ✓
  - [x] updateContent() ✓
  - [x] destroy() ✓

- [x] **All high-impact operations logged**
  - [x] Create (TEMPLATE_CREATE)
  - [x] Upload (TEMPLATE_UPLOAD)
  - [x] Update Draft (TEMPLATE_UPDATE_DRAFT)
  - [x] Issue/Activate (TEMPLATE_ISSUE_ACTIVATE)
  - [x] Deactivate (TEMPLATE_DEACTIVATE)
  - [x] Preview (TEMPLATE_PREVIEW)
  - [x] Delete (TEMPLATE_DELETE)

- [x] **Audit logs include minimum required data**
  - [x] actor_id (from Auth::id())
  - [x] action (operation name)
  - [x] template_id (in context or before/after)
  - [x] doc_type (in context)
  - [x] before state (where applicable)
  - [x] after state (where applicable)

---

## Files Changed

```
app/Http/Controllers/Api/Settings/DocumentTemplateController.php  (MODIFIED - 9 changes)
  ├─ store(): Added TEMPLATE_CREATE audit log
  ├─ update(): Added TEMPLATE_UPDATE_DRAFT audit log
  ├─ activate(): Enhanced TEMPLATE_ISSUE_ACTIVATE audit log
  ├─ deactivate(): Enhanced TEMPLATE_DEACTIVATE audit log
  ├─ upload(): Enhanced TEMPLATE_UPLOAD audit log
  ├─ destroy(): Enhanced TEMPLATE_DELETE audit log
  ├─ previewTemplateHtml(): Added TEMPLATE_PREVIEW audit log
  ├─ previewTemplatePdf(): Added TEMPLATE_PREVIEW audit log
  └─ preview(): Added TEMPLATE_PREVIEW audit log
```

**Status:** ✅ Complete - All template operations audited with before/after state tracking
