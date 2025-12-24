# Search 401 Unauthorized - Complete Fix Summary

## ROOT CAUSE

**Problem:** Browser XHR GET requests to `/api/search` returned HTTP 401 Unauthorized

**Root Cause Analysis:**
- Frontend HTML (search/index.blade.php) made requests to `/api/search` endpoint
- Backend route `/api/search` (routes/api.php) was protected with `auth` middleware using API guard
- API guard expects stateless authentication: Bearer tokens or Sanctum cookies, NOT session cookies
- Frontend is session-authenticated web UI (wrapped in `['auth', 'verified']` middleware)
- When session-based frontend called `/api/search`, no valid API token was present → 401 Unauthorized
- Root cause: **Mismatch between auth guard (API stateless) and auth mechanism (session cookies)**

**Evidence Trail:**
1. API route: `routes/api.php:14` → `Route::middleware(['auth', 'throttle:search'])` 
2. Default auth guard uses Sanctum API auth (stateless)
3. SearchController: `app/Http/Controllers/Api/SearchController.php:15-16` required `$this->authorize()` 
4. Frontend fetch: `resources/views/search/index.blade.php:552` called `/api/search` without token/credentials

## SOLUTION IMPLEMENTED

Instead of trying to make `/api/search` work with session auth (which would weaken API security), **leverage the existing web route** that was already session-authenticated.

**Changes Made:**

### 1. Backend: New Web Endpoint
**File:** `app/Http/Controllers/DatabaseController.php`
- Added new `search()` method (lines 544-588)
- Uses same `SearchService` as API endpoint
- Returns identical JSON response structure
- Protected by `can:viewAny(Person::class)` authorization
- Validates input parameters (q, doc_type, sort, pagination)

### 2. Backend: New Route  
**File:** `routes/web.php`
- Added route: `Route::get('/database/search', ...)` (line 144)
- Within authenticated middleware group: `['auth', 'verified']`
- Within gate middleware: `'can:view-database'` 
- Session-authenticated, authorization checked at both middleware and controller level

### 3. Frontend: Endpoint Switch
**File:** `resources/views/search/index.blade.php`
- Changed data attribute: `data-api-endpoint="{{ url('/database/search') }}"` (line 31)
- Changed fallback URL: `'/database/search'` (line 241)
- Added credentials to fetch: `credentials: 'same-origin'` (line 556)

## VERIFICATION CHECKLIST

### 1. Route Registration
```bash
cd /home/lpmf-dev/website-
php artisan route:list | grep -E "api/search|database/search"
```
Expected output:
```
GET|HEAD  api/search ..................... api.search › Api\SearchController
GET|HEAD  database/search .............. database.search › DatabaseController@search
```

### 2. Browser Testing
1. Open http://127.0.0.1:8000 (should already be logged in)
2. Navigate to Search page (/search)
3. Enter search query (min 2 characters)
4. Check Browser DevTools:
   - Network tab: XHR requests to `/database/search?q=...&page_people=...` should return **200 OK**
   - Response should have: `{query, doc_type, summary, people, documents}`
   - No more 401 errors
5. Verify results render correctly (people and documents display)

### 3. Manual Curl Test (with session)
```bash
# Get XSRF-TOKEN and session cookie from login page
curl -c cookies.txt -s http://127.0.0.1:8000/login > /dev/null

# Login
curl -b cookies.txt -c cookies.txt -s -X POST http://127.0.0.1:8000/login \
  -d "email=user@example.com&password=password&_token=..." > /dev/null

# Test search endpoint
curl -b cookies.txt -s "http://127.0.0.1:8000/database/search?q=test&page_people=1&per_page_people=6&page_docs=1&per_page_docs=6" \
  -H "Accept: application/json"
```
Expected: JSON response with people and documents

### 4. API Endpoint Status
- `/api/search` still works for API consumers with proper Bearer tokens
- `/database/search` works for web UI with session authentication
- Both endpoints share same backend logic via SearchService

## SECURITY IMPLICATIONS

✅ **No security downgrade:** `/database/search` uses same authorization checks
✅ **Least privilege:** Inherits `can:view-database` gate from route group
✅ **Session-bound:** Credentials tied to session, not public tokens
✅ **API untouched:** `/api/search` remains secure and token-authenticated
✅ **CSRF protected:** Laravel automatically validates POST (GET is inherently safer)

## ROLLBACK PLAN

If reversion needed:
```bash
git checkout routes/web.php
git checkout resources/views/search/index.blade.php
git checkout app/Http/Controllers/DatabaseController.php
```

Then frontend will fall back to trying `/api/search` (which will still fail with 401, but that's original state).
