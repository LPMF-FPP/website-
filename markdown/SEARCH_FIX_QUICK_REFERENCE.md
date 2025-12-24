# Search Fix - Quick Reference

## What Was Fixed

The search feature now correctly returns:

✓ **Nama Penyidik** - Investigator names from `investigators` table
✓ **Nama Tersangka** - Suspect names from `test_requests.suspect_name`
✓ **Foto Sampel** - Documents with `document_type='sample_photo'`
✓ **Berita Acara Penerimaan Sampel** - Documents with `document_type='ba_penerimaan'`
✓ **Berita Acara Penyerahan Sampel** - Documents with `document_type='ba_penyerahan'`
✓ **Surat Permintaan** - Documents with `document_type='request_letter'`

Plus correct download/preview URLs for each.

## Root Cause

The old `SearchService` was querying non-existent tables. The new implementation:

1. Queries the actual app tables: `investigators`, `test_requests`, `documents`
2. Uses proper PostgreSQL ILIKE syntax (removed bad ESCAPE clause)
3. Joins tables to include context (request number, suspect name)
4. Returns proper URLs for document downloads/previews

## Key Changes

### SearchService (`app/Services/Search/SearchService.php`)
- **searchInvestigatorsAndRequests()** - Searches investigators by name + test requests by suspect/request number
- **searchDocumentsWithDeliveries()** - Searches documents with context joins
- Both return paginated results with relevance scoring

### Resources
- **SearchPeopleResource** - Handles investigators and test_requests
- **SearchDocumentResource** - Handles document metadata with URLs

### Controller  
- **DatabaseController@search** - Fixed resource transformation for stdClass objects

## Database Structure

```
investigators
├─ id
├─ name
└─ rank

test_requests
├─ id
├─ request_number
├─ suspect_name
└─ investigator_id [FK]

documents
├─ id
├─ document_type (sample_photo, ba_penerimaan, ba_penyerahan, request_letter)
├─ original_filename
├─ test_request_id [FK]
├─ investigator_id [FK]
└─ file_path
```

## Testing

```bash
# Quick test - search directly
php test_search.php

# Verify routes
php artisan route:list | grep search

# Check document types
php artisan tinker
DB::table('documents')->distinct('document_type')->pluck('document_type');
```

## Result Example

Searching for "Resbob" now returns:

**People Results:**
- 1 test_request: "Resbob (Permintaan Pengujian #REQ-2025-0001)" by "John Doe"

**Document Results:**
- BA-Penerimaan-REQ-2025-0001.pdf (ba_penerimaan)
- 35c58704d8812a9cd32e2ce30121ed6e.jpg (sample_photo)
- dummy-pdf_2.pdf (request_letter)

All with working download/preview URLs ✓

## Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Search/SearchService.php` | Complete rewrite |
| `app/Http/Resources/SearchPeopleResource.php` | Updated format |
| `app/Http/Resources/SearchDocumentResource.php` | Updated format |
| `app/Http/Controllers/DatabaseController.php` | Fixed resource handling |

## How It Works

1. User enters search query (e.g., "Resbob")
2. `SearchRequest` validates and escapes input
3. `SearchService` searches:
   - `investigators.name ILIKE '%Resbob%'`
   - `test_requests.suspect_name ILIKE '%Resbob%'`
   - `test_requests.request_number ILIKE '%Resbob%'`
   - `documents.original_filename ILIKE '%Resbob%'` (joined with test_requests context)
4. Results are scored by relevance and paginated
5. Resources transform results into JSON
6. Frontend displays results with clickable download/preview links

## Security

- All queries use parameterized statements (no SQL injection)
- Authorization checks still in place (`can:view-database`)
- No new security issues introduced
- All routes remain protected

## No Breaking Changes

✓ API response structure unchanged
✓ Parameter names unchanged  
✓ Pagination unchanged
✓ Download/preview routes unchanged
✓ Authorization unchanged
✓ No database migrations needed
