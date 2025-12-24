# Blade Template Editor - Preview Feature

## Overview
The preview feature allows you to see the rendered output of Blade templates with sample data before saving changes.

## How to Use

### From the Editor
1. Open a template in the Blade Template Editor
2. Make your edits
3. Click the **"Preview"** button in the toolbar
4. A modal will open showing the rendered HTML with sample data
5. Review the output
6. Close the preview and continue editing or save

### Preview Button Location
The Preview button is located in the toolbar between "Simpan" and "Riwayat":
```
[Simpan] [Preview] [Riwayat] [Batal]
```

## Sample Data

The preview feature uses realistic sample data for each template type:

### 1. Berita Acara Penerimaan
- Request number: REQ-2025-0001
- Case number: B/001/I/2025/Reskrim
- Investigator: IPDA Budi Santoso
- 2 sample items (Pil Ekstasi, Bubuk Putih Kristal)
- Test methods: GC-MS, UV-VIS

### 2. BA Penyerahan
- Request number: REQ-2025-0001
- BA number: BA-001/LPMF/I/2025
- Sample code: W-001-2025
- Quantities and packaging details

### 3. Laporan Hasil Uji
- Report number: FLHU-001/LPMF/I/2025
- Test method: GC-MS
- Test result: Positive for MDMA
- Complete customer and sample information

### 4. Form Preparation
- Sample: Pil Ekstasi Warna Biru
- Sample code: W-001-2025
- Analyst: Dr. Ahmad Fauzi, S.Si., Apt.

## Technical Details

### API Endpoint
```
POST /api/settings/blade-templates/{template}/preview
```

### Request Body
```json
{
  "content": "<!-- Blade template content -->"
}
```

### Response (Success)
```json
{
  "success": true,
  "html": "<!DOCTYPE html>..."
}
```

### Response (Error)
```json
{
  "success": false,
  "message": "Error message",
  "errors": ["List of specific errors"]
}
```

## Security

The preview feature includes the same security validations as the save function:
- ✅ Dangerous function checking (exec, eval, etc.)
- ✅ Permission validation (manage-settings required)
- ✅ CSRF protection
- ✅ Template whitelist enforcement

Dangerous code will be rejected before preview generation.

## How It Works

1. **Temporary File Creation**: Creates a temporary Blade file in `storage/app/`
2. **Sample Data Injection**: Injects realistic sample data for the template type
3. **Rendering**: Uses Laravel's view system to render the template
4. **Cleanup**: Automatically deletes temporary files
5. **Display**: Shows rendered HTML in an iframe with auto-height adjustment

## Features

### Modal Preview Window
- Large 6xl modal for comfortable viewing
- Auto-height iframe based on content
- Scrollable for long documents
- Keyboard support (ESC to close)

### Loading States
- Spinner animation while generating preview
- Error messages if preview fails
- Clear feedback for dangerous code detection

### Iframe Sandbox
- Safe rendering in isolated iframe
- Prevents script execution from preview content
- Auto-resizes to fit content (min 600px)

## Limitations

1. **Static Preview**: Preview shows HTML output only, not PDF
2. **Sample Data**: Uses fixed sample data, not real database data
3. **No Interactivity**: Preview is read-only, no form submission
4. **CSS Dependencies**: External CSS must be inline or use data URIs

## Error Handling

### Common Errors

**"Template mengandung kode yang tidak diizinkan"**
- Your template contains dangerous PHP functions
- Remove functions like `exec()`, `eval()`, etc.
- Check the error list for specific violations

**"Gagal membuat preview"**
- Syntax error in Blade template
- Missing variables or incorrect data structure
- Check error message for line number and details

**Preview shows broken layout**
- Missing CSS or incorrect paths
- Use inline styles or data URIs for images
- Check browser console for asset loading errors

## Tips

### For Better Previews

1. **Use Inline Styles**: Embed CSS directly in `<style>` tags
2. **Data URIs for Images**: Convert images to base64 data URIs
3. **Test Variables**: Ensure all variables used exist in sample data
4. **Responsive Design**: Preview modal is 6xl wide (~72rem)

### Quick Preview Workflow

```
Edit → Preview → Adjust → Preview → Save
```

Always preview before saving major changes to catch errors early.

## Keyboard Shortcuts

- **ESC**: Close preview modal
- (Click outside modal also closes it)

## Browser Compatibility

Preview works in all modern browsers:
- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+

## Future Enhancements

Potential improvements:
- [ ] PDF preview (via DOMPDF/WeasyPrint)
- [ ] Side-by-side code/preview
- [ ] Multiple sample data sets
- [ ] Print preview button
- [ ] Download preview as HTML

---

**Last Updated**: December 23, 2025  
**Version**: 1.1.0
