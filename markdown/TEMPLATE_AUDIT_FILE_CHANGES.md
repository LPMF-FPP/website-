# Template Audit Logging - File Changes

## Modified Files

### 1. `app/Http/Controllers/Api/Settings/DocumentTemplateController.php`

**Changes:** 9 audit log additions for high-impact operations

---

## Patch Details

### Patch 1: CREATE - store() method
**Location:** Line ~175  
**Operation:** Add audit log after template creation

```diff
         $template = $this->repository->createTemplateVersion([
             'type' => $data['type'],
             'format' => $data['format'],
             'name' => $data['name'],
             'content_html' => $data['content_html'],
             'content_css' => $data['content_css'] ?? null,
             'editor_project' => $data['editor_project'] ?? null,
             'render_engine' => $data['render_engine'] ?? DocumentRenderEngine::BROWSERSHOT->value,
             'checksum' => md5($data['content_html']),
             'is_active' => (bool) ($data['is_active'] ?? false),
             'meta' => array_merge($data['meta'] ?? [], [
                 'editor' => 'grapesjs',
                 'created_via' => 'template_editor',
             ]),
             'created_by' => $request->user()->id,
             'updated_by' => $request->user()->id,
         ]);

+        Audit::log(
+            action: 'TEMPLATE_CREATE',
+            target: $template->code ?? "template_{$template->id}",
+            before: null,
+            after: [
+                'template_id' => $template->id,
+                'name' => $template->name,
+                'doc_type' => $template->type->value,
+                'version' => $template->version,
+                'is_active' => $template->is_active,
+            ],
+            context: [
+                'template_id' => $template->id,
+                'doc_type' => $template->type->value,
+                'format' => $template->format->value,
+                'render_engine' => $template->render_engine?->value,
+            ]
+        );
+
         return response()->json([
             'message' => 'Template created',
             'template' => $template,
         ], 201);
```

---

### Patch 2: UPDATE DRAFT - update() method
**Location:** Line ~230  
**Operation:** Add audit log after draft update with before/after comparison

```diff
         $newTemplate = $this->repository->createTemplateVersion([
             'type' => $data['type'],
             'format' => $data['format'],
             'name' => $data['name'],
             'content_html' => $data['content_html'],
             'content_css' => $data['content_css'] ?? null,
             'editor_project' => $data['editor_project'] ?? null,
             'render_engine' => $data['render_engine'] ?? $template->render_engine?->value ?? DocumentRenderEngine::BROWSERSHOT->value,
             'checksum' => md5($data['content_html']),
             'is_active' => (bool) ($data['is_active'] ?? $template->is_active),
             'meta' => array_merge($template->meta ?? [], $data['meta'] ?? [], [
                 'editor' => 'grapesjs',
                 'updated_via' => 'template_editor',
                 'source_template_id' => $template->id,
             ]),
             'created_by' => $request->user()->id,
             'updated_by' => $request->user()->id,
         ]);

+        Audit::log(
+            action: 'TEMPLATE_UPDATE_DRAFT',
+            target: $template->code ?? "template_{$template->id}",
+            before: [
+                'template_id' => $template->id,
+                'name' => $template->name,
+                'version' => $template->version,
+                'checksum' => $template->checksum,
+                'is_active' => $template->is_active,
+            ],
+            after: [
+                'template_id' => $newTemplate->id,
+                'name' => $newTemplate->name,
+                'version' => $newTemplate->version,
+                'checksum' => $newTemplate->checksum,
+                'is_active' => $newTemplate->is_active,
+            ],
+            context: [
+                'old_template_id' => $template->id,
+                'new_template_id' => $newTemplate->id,
+                'doc_type' => $template->type->value,
+                'content_changed' => $template->checksum !== $newTemplate->checksum,
+            ]
+        );
+
         return response()->json([
             'message' => 'Template updated',
             'template' => $newTemplate,
         ]);
```

---

### Patch 3: ISSUE/ACTIVATE - activate() method
**Location:** Line ~315  
**Operation:** Enhance audit log with previous active template tracking

```diff
         try {
+            // Get template state before activation
+            $oldTemplate = DocumentTemplate::find($templateId);
+            $previousActiveTemplate = DocumentTemplate::where('type', $oldTemplate->type)
+                ->where('is_active', true)
+                ->where('id', '!=', $templateId)
+                ->first();
+
             $template = $this->repository->activateTemplate($templateId);

-            Audit::log('ACTIVATE_TEMPLATE', $template->code, null, [
-                'template_id' => $template->id,
-                'type' => $template->type->value,
-                'format' => $template->format->value,
-            ]);
+            Audit::log(
+                action: 'TEMPLATE_ISSUE_ACTIVATE',
+                target: $template->code ?? "template_{$template->id}",
+                before: [
+                    'template_id' => $oldTemplate->id,
+                    'is_active' => $oldTemplate->is_active,
+                    'previous_active_template_id' => $previousActiveTemplate?->id,
+                    'previous_active_template_name' => $previousActiveTemplate?->name,
+                ],
+                after: [
+                    'template_id' => $template->id,
+                    'is_active' => $template->is_active,
+                    'version' => $template->version,
+                    'name' => $template->name,
+                ],
+                context: [
+                    'template_id' => $template->id,
+                    'doc_type' => $template->type->value,
+                    'format' => $template->format->value,
+                    'deactivated_template_id' => $previousActiveTemplate?->id,
+                ]
+            );

             return response()->json([
                 'message' => 'Template activated successfully',
                 'template' => $template,
             ]);
```

---

### Patch 4: DEACTIVATE - deactivate() method
**Location:** Line ~355  
**Operation:** Enhance audit log with before/after state

```diff
         try {
+            $oldTemplate = DocumentTemplate::find($templateId);
             $template = $this->repository->deactivateTemplate($templateId);

-            Audit::log('DEACTIVATE_TEMPLATE', $template->code, null, [
-                'template_id' => $template->id,
-            ]);
+            Audit::log(
+                action: 'TEMPLATE_DEACTIVATE',
+                target: $template->code ?? "template_{$template->id}",
+                before: [
+                    'template_id' => $oldTemplate->id,
+                    'is_active' => $oldTemplate->is_active,
+                ],
+                after: [
+                    'template_id' => $template->id,
+                    'is_active' => $template->is_active,
+                ],
+                context: [
+                    'template_id' => $template->id,
+                    'doc_type' => $template->type->value,
+                ]
+            );

             return response()->json([
                 'message' => 'Template deactivated successfully',
                 'template' => $template,
             ]);
```

---

### Patch 5: PREVIEW HTML - previewTemplateHtml() method
**Location:** Line ~270  
**Operation:** Add audit log before rendering preview

```diff
     public function previewTemplateHtml(DocumentTemplate $template, Request $request): Response
     {
         Gate::authorize('manage-settings');

         try {
+            Audit::log(
+                action: 'TEMPLATE_PREVIEW',
+                target: $template->code ?? "template_{$template->id}",
+                before: null,
+                after: null,
+                context: [
+                    'template_id' => $template->id,
+                    'doc_type' => $template->type->value,
+                    'format' => 'html',
+                    'preview_type' => 'template_html',
+                ]
+            );
+
             $context = $this->renderService->getSampleContext($template->type);
             $document = $this->templateRenderService->renderHtml($template, $template->type, $context, ['preview' => true]);
             return $document->toInlineResponse();
```

---

### Patch 6: PREVIEW PDF - previewTemplatePdf() method
**Location:** Line ~295  
**Operation:** Add audit log before rendering PDF preview

```diff
     public function previewTemplatePdf(DocumentTemplate $template, Request $request): Response
     {
         Gate::authorize('manage-settings');

         try {
+            Audit::log(
+                action: 'TEMPLATE_PREVIEW',
+                target: $template->code ?? "template_{$template->id}",
+                before: null,
+                after: null,
+                context: [
+                    'template_id' => $template->id,
+                    'doc_type' => $template->type->value,
+                    'format' => 'pdf',
+                    'preview_type' => 'template_pdf',
+                ]
+            );
+
             $context = $this->renderService->getSampleContext($template->type);
             $document = $this->templateRenderService->renderPdf($template, $template->type, $context, ['preview' => true]);
             return $document->toInlineResponse();
```

---

### Patch 7: PREVIEW GENERAL - preview() method
**Location:** Line ~420  
**Operation:** Add audit log for general preview endpoint

```diff
             if (!in_array($docFormat, $docType->supportedFormats())) {
                 return response()->json([
                     'message' => 'Format not supported',
                     'error' => "Document type '{$type}' does not support '{$format}' format",
                 ], 422);
             }

+            Audit::log(
+                action: 'TEMPLATE_PREVIEW',
+                target: "preview_{$type}_{$format}",
+                before: null,
+                after: null,
+                context: [
+                    'doc_type' => $type,
+                    'format' => $format,
+                    'preview_type' => 'general_preview',
+                ]
+            );
+
             $rendered = $this->renderService->renderPreview($docType, $docFormat, [
                 'audit' => false, // Don't audit previews
             ]);

             return $rendered->toInlineResponse();
```

---

### Patch 8: UPLOAD - upload() method
**Location:** Line ~500  
**Operation:** Enhance audit log with file details

```diff
             'created_by' => $request->user()->id,
             'updated_by' => $request->user()->id,
         ]);

-        Audit::log('UPLOAD_TEMPLATE', $template->code, null, [
-            'template_id' => $template->id,
-            'type' => $docType->value,
-            'format' => $format->value,
-        ]);
+        Audit::log(
+            action: 'TEMPLATE_UPLOAD',
+            target: $template->code ?? "template_{$template->id}",
+            before: null,
+            after: [
+                'template_id' => $template->id,
+                'name' => $template->name,
+                'version' => $template->version,
+                'storage_path' => $template->storage_path,
+            ],
+            context: [
+                'template_id' => $template->id,
+                'doc_type' => $docType->value,
+                'format' => $format->value,
+                'original_filename' => $template->meta['original_filename'] ?? null,
+                'file_size' => $request->file('file')->getSize(),
+            ]
+        );

         return response()->json($template, 201);
```

---

### Patch 9: DELETE - destroy() method
**Location:** Line ~610  
**Operation:** Add before state and check template existence

```diff
         try {
+            // Get template before deletion
+            $template = DocumentTemplate::find($templateId);
+            if (!$template) {
+                return response()->json([
+                    'message' => 'Template not found',
+                ], 404);
+            }
+
+            Audit::log(
+                action: 'TEMPLATE_DELETE',
+                target: $template->code ?? "template_{$templateId}",
+                before: [
+                    'template_id' => $template->id,
+                    'name' => $template->name,
+                    'doc_type' => $template->type->value,
+                    'version' => $template->version,
+                    'is_active' => $template->is_active,
+                ],
+                after: null,
+                context: [
+                    'template_id' => $templateId,
+                    'doc_type' => $template->type->value,
+                    'was_active' => $template->is_active,
+                ]
+            );
+
             $this->repository->deleteTemplate($templateId);

-            Audit::log('DELETE_TEMPLATE', "template_{$templateId}", null, [
-                'template_id' => $templateId,
-            ]);

             return response()->json([
                 'message' => 'Template deleted successfully',
             ]);
```

---

## Summary

**Total patches:** 9  
**Lines added:** ~150  
**Methods affected:** 9/13 (4 already had basic audit logs, enhanced all)

### Operations with NEW audit logs:
1. ✅ `store()` - TEMPLATE_CREATE
2. ✅ `update()` - TEMPLATE_UPDATE_DRAFT  
3. ✅ `previewTemplateHtml()` - TEMPLATE_PREVIEW
4. ✅ `previewTemplatePdf()` - TEMPLATE_PREVIEW
5. ✅ `preview()` - TEMPLATE_PREVIEW

### Operations with ENHANCED audit logs:
1. ✅ `activate()` - TEMPLATE_ISSUE_ACTIVATE (added before state + previous active)
2. ✅ `deactivate()` - TEMPLATE_DEACTIVATE (added before/after)
3. ✅ `upload()` - TEMPLATE_UPLOAD (added after state + file details)
4. ✅ `destroy()` - TEMPLATE_DELETE (added before state + existence check)

### Gate checks:
✅ All 13 endpoints already have `Gate::authorize('manage-settings')`  
✅ No additional gate changes needed

---

## Verification Steps

### 1. Check audit log after create:
```sql
SELECT * FROM audit_logs 
WHERE action = 'TEMPLATE_CREATE' 
ORDER BY id DESC LIMIT 1;
```

Expected fields:
- `actor_id` = current user ID
- `action` = 'TEMPLATE_CREATE'
- `before` = null
- `after` = {template_id, name, doc_type, version, is_active}
- `context` = {doc_type, format, render_engine}

### 2. Check audit log after activate:
```sql
SELECT * FROM audit_logs 
WHERE action = 'TEMPLATE_ISSUE_ACTIVATE' 
ORDER BY id DESC LIMIT 1;
```

Expected fields:
- `before` = {is_active: false, previous_active_template_id, previous_active_template_name}
- `after` = {is_active: true, version, name}
- `context` = {deactivated_template_id}

### 3. Check all template operations:
```sql
SELECT action, COUNT(*) as count
FROM audit_logs
WHERE action LIKE 'TEMPLATE_%'
GROUP BY action
ORDER BY count DESC;
```

Expected actions:
- TEMPLATE_CREATE
- TEMPLATE_UPLOAD
- TEMPLATE_UPDATE_DRAFT
- TEMPLATE_ISSUE_ACTIVATE
- TEMPLATE_DEACTIVATE
- TEMPLATE_PREVIEW
- TEMPLATE_DELETE

---

## Status
✅ All patches applied successfully  
✅ All high-impact operations logged  
✅ Before/after state tracking complete
