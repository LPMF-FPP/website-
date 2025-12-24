# Blade Template Editor - Web-Based PDF Template Editing

## Overview

The Blade Template Editor is a web-based interface that allows authorized users to edit PDF Blade templates directly from the browser without needing file system access. This feature includes built-in security, backup/restore functionality, and syntax validation.

## Features

### ✅ Core Functionality
- **Web-Based Editor**: Edit Blade templates directly in the browser
- **Real-Time Preview**: See changes before saving
- **Auto-Backup**: Automatic backup creation before each save
- **Version History**: View and restore from up to 20 previous backups
- **Security Validation**: Prevents dangerous PHP functions from being saved
- **Cache Clearing**: Automatically clears view cache after saves
- **Change Detection**: Warns before navigating away with unsaved changes

### 🔒 Security Features
1. **Permission-Based Access**: Only users with `manage-settings` permission can access
2. **Whitelist Approach**: Only specific templates are editable (security by design)
3. **Function Blacklist**: Dangerous PHP functions are blocked (exec, eval, shell_exec, etc.)
4. **Audit Logging**: All edit attempts are logged with user info and IP
5. **CSRF Protection**: All mutations require valid CSRF token
6. **Backup Before Restore**: Creates backup of current version before restoring

## Editable Templates

The following templates can be edited via the web interface:

1. **Berita Acara Penerimaan** (`berita-acara-penerimaan.blade.php`)
2. **BA Penyerahan** (`ba-penyerahan.blade.php`)
3. **Laporan Hasil Uji** (`laporan-hasil-uji.blade.php`)
4. **Form Preparation** (`form-preparation.blade.php`)

All templates are located in `resources/views/pdf/`

## Usage

### Accessing the Editor

1. Navigate to **Settings** → **Blade Templates** or directly visit:
   ```
   /settings/blade-templates
   ```

2. You must have the `manage-settings` permission to access this page.

### Editing a Template

1. **Select Template**: Click on a template card to load it
2. **Make Changes**: Edit the code in the text editor
3. **Save**: Click "Simpan" button (auto-creates backup)
4. **View History**: Click "Riwayat" to see backups

### Restoring from Backup

1. Click "Riwayat" button while editing a template
2. Select a backup from the list (sorted by date, newest first)
3. Click "Pulihkan" to restore
4. Confirm the restoration (current version is backed up first)

## API Endpoints

All endpoints require authentication and `manage-settings` permission.

### `GET /api/settings/blade-templates`
List all editable templates with metadata.

**Response:**
```json
{
  "success": true,
  "templates": [
    {
      "key": "berita-acara-penerimaan",
      "name": "Berita Acara Penerimaan",
      "path": "resources/views/pdf/berita-acara-penerimaan.blade.php",
      "size": 12345,
      "modified_at": "2025-12-23T10:30:00+07:00",
      "editable": true
    }
  ]
}
```

### `GET /api/settings/blade-templates/{template}`
Get template content.

**Parameters:**
- `template` (string): Template key (e.g., `berita-acara-penerimaan`)

**Response:**
```json
{
  "success": true,
  "template": {
    "key": "berita-acara-penerimaan",
    "name": "Berita Acara Penerimaan",
    "path": "resources/views/pdf/berita-acara-penerimaan.blade.php",
    "content": "<!DOCTYPE html>...",
    "size": 12345,
    "modified_at": "2025-12-23T10:30:00+07:00"
  }
}
```

### `PUT /api/settings/blade-templates/{template}`
Update template content.

**Request Body:**
```json
{
  "content": "<!DOCTYPE html>...",
  "create_backup": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Template berhasil disimpan.",
  "template": {
    "key": "berita-acara-penerimaan",
    "size": 12350,
    "modified_at": "2025-12-23T10:35:00+07:00"
  }
}
```

### `GET /api/settings/blade-templates/{template}/backups`
List all backups for a template.

**Response:**
```json
{
  "success": true,
  "backups": [
    {
      "filename": "2025-12-23_103000.blade.php",
      "path": "template-backups/berita-acara-penerimaan/2025-12-23_103000.blade.php",
      "size": 12340,
      "created_at": "2025-12-23T10:30:00+07:00"
    }
  ]
}
```

### `POST /api/settings/blade-templates/{template}/restore`
Restore template from backup.

**Request Body:**
```json
{
  "backup_file": "template-backups/berita-acara-penerimaan/2025-12-23_103000.blade.php"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Template berhasil dipulihkan dari backup."
}
```

## Security Implementation

### Dangerous Function Blacklist

The following PHP functions are blocked:
- `exec`, `shell_exec`, `system`, `passthru`
- `proc_open`, `popen`
- `eval`, `assert`, `create_function`
- `file_put_contents`, `file_get_contents`
- `unlink`, `rmdir`, `chmod`, `chown`
- `curl_exec`, `curl_multi_exec`

### Template Whitelist

Only templates defined in `BladeTemplateEditorController::EDITABLE_TEMPLATES` can be edited. To add a new template:

```php
private const EDITABLE_TEMPLATES = [
    'template-key' => 'resources/views/pdf/template-name.blade.php',
];
```

### Audit Logging

All edit attempts are logged to the `audit` log channel:

```php
[2025-12-23 10:35:00] audit.INFO: Blade template edit attempt
{
  "user_id": 1,
  "user_email": "admin@example.com",
  "ip": "127.0.0.1",
  "template": "berita-acara-penerimaan",
  "method": "PUT",
  "user_agent": "Mozilla/5.0..."
}
```

## Backup System

### Auto-Backup Behavior
- Backups are created automatically before each save
- Backups are stored in `storage/app/template-backups/{template-key}/`
- Only the last 20 backups are kept (older ones are auto-deleted)
- Backup filename format: `YYYY-MM-DD_HHmmss.blade.php`

### Restore Behavior
- Current template is backed up with `_before-restore` suffix before restoration
- Original backup file is preserved
- View cache is cleared after restoration

## Error Handling

### Validation Errors
If dangerous functions are detected:
```json
{
  "success": false,
  "message": "Template mengandung kode yang tidak diizinkan.",
  "errors": [
    "Fungsi PHP berbahaya terdeteksi: exec()"
  ]
}
```

### Permission Errors
```json
{
  "success": false,
  "message": "Template tidak ditemukan atau tidak diizinkan untuk diedit."
}
```

## Best Practices

### When Editing Templates

1. **Test in Development First**: Always test changes in a dev environment
2. **Use Version Control**: Commit changes to Git after testing
3. **Document Changes**: Add comments explaining major modifications
4. **Backup Before Major Changes**: Download a manual backup before big edits
5. **Test Generated PDFs**: Generate actual PDFs after saving to verify output

### Safe Blade Syntax

✅ **Safe to use:**
```blade
{{ $variable }}
@if($condition)
@foreach($items as $item)
@php
    $localVar = 'value';
@endphp
```

❌ **Avoid:**
```blade
@php
    exec('rm -rf /');  // Blocked by validator
@endphp
{{ eval($userInput) }}  // Dangerous
```

## Troubleshooting

### "Template tidak ditemukan"
- Ensure the template key matches exactly (case-sensitive)
- Check that the template exists in `EDITABLE_TEMPLATES` constant

### "Gagal menyimpan template"
- Check file permissions on `resources/views/pdf/` directory
- Ensure web server user has write access
- Check for disk space issues

### "Validasi gagal"
- Review the error messages for specific dangerous functions
- Remove or refactor the flagged code
- Consider using Blade directives instead of raw PHP

### Backups not appearing
- Check `storage/app/template-backups/` directory exists and is writable
- Ensure `local` disk is configured in `config/filesystems.php`

## Files Modified/Created

### New Files
- `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php` - Main controller
- `app/Http/Middleware/ValidateBladeTemplateAccess.php` - Security middleware
- `resources/views/settings/blade-templates.blade.php` - Frontend UI
- `BLADE_TEMPLATE_EDITOR.md` - This documentation

### Modified Files
- `routes/api.php` - Added API endpoints
- `routes/web.php` - Added web route for editor page

## Future Enhancements

Potential improvements for future versions:

- [ ] Syntax highlighting with CodeMirror or Monaco Editor
- [ ] Live PDF preview (iframe with generated PDF)
- [ ] Diff viewer for comparing versions
- [ ] Search & replace functionality
- [ ] Template variable reference panel
- [ ] Export/import template functionality
- [ ] Multi-user edit conflict detection
- [ ] Template testing with mock data

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review audit logs in `storage/logs/laravel.log`
3. Verify permissions with `php artisan gate:check manage-settings`
4. Contact your system administrator

---

**Version:** 1.0.0  
**Last Updated:** December 23, 2025  
**Compatibility:** Laravel 11.x, PHP 8.2+
