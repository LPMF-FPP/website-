# Search 401 Unauthorized Fix

## Root Cause Analysis

**Problem:** Browser XHR GET requests to `/api/search` return HTTP 401 Unauthorized

**Root Cause:**
- Frontend: `resources/views/search/index.blade.php` makes requests to `/api/search` endpoint
- Backend: `routes/api.php` protects `/api/search` with `auth` middleware (line 9-16), which requires API authentication
- The auth middleware is configured for API guard which expects Sanctum stateless auth (bearer tokens or stateful cookies)
- Frontend is a session-authenticated web page, not an SPA with API tokens
- When session-auth frontend calls `/api/search`, no valid API token/Sanctum cookie present → 401

**Evidence:**
- API route middleware: `Route::middleware(['auth', 'throttle:search'])->group(...)`
- SearchController requires both Person and Document authorization (line 16-17)
- API SearchController at `app/Http/Controllers/Api/SearchController.php` 
- Web route exists: `/database/suggest` at `routes/web.php:143` with `can:view-database` middleware
- DatabaseController::suggest() already returns search results (lines 433-543)

## Solution
Replace `/api/search` calls with `/database/suggest` endpoint that:
1. Already has session-auth via `can:view-database` middleware
2. Returns JSON-compatible response format
3. Requires minimal frontend changes

## Implementation Steps
1. Create new web endpoint at `/database/search` that returns full search results (not just suggestions)
2. Update frontend to call new endpoint with proper params
3. Add credentials to fetch call
4. Test in browser
