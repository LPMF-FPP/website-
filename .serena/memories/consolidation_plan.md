# Consolidation Plan: /database → /search Canonical Route

## CURRENT STATE

### Routes (routes/web.php:140-162)
- `GET /database` → `DatabaseController@index` (name: `database.index`) [renders: database/index.blade.php]
- `GET /database/suggest` → `DatabaseController@suggest` (name: `database.suggest`)
- `GET /database/search` → `DatabaseController@search` (name: `database.search`) [XHR endpoint]
- `GET /search` → Route::view('search.index') (name: `search`) [renders: search/index.blade.php]

All routes wrapped in middleware: `['auth', 'verified', 'can:view-database']`

### Controllers
- **DatabaseController**: index() does complex filtering/pagination, renders database/index.blade.php
- **DatabaseController**: search() wraps SearchService, returns JSON for XHR
- **DatabaseController**: suggest() returns autocomplete suggestions

### Views
- **database/index.blade.php**: Full-featured database listing with filters, pagination, statistics
- **search/index.blade.php**: Slim view with Alpine.js, calls /database/search XHR endpoint

### Problem
Two separate interfaces for same data:
1. `/database` (traditional server-rendered page, complex filters)
2. `/search` (lightweight AJAX-based search interface)
This is redundant. Goal: consolidate to `/search` as canonical.

## CONSOLIDATION PLAN

### Step 1: Create SearchController (new file)
Create `app/Http/Controllers/SearchController.php` with:
- `index()` - calls same logic as DatabaseController@index, returns view('search.index')
- `suggest()` - delegates to DatabaseController or duplicates (reuse)

### Step 2: Update routes (routes/web.php)
OLD:
```php
Route::get('/search', ...)->view('search.index')
Route::get('/database', [DatabaseController@index])
Route::get('/database/suggest', [DatabaseController@suggest])
Route::get('/database/search', [DatabaseController@search])
```

NEW:
```php
// Canonical route
Route::get('/search', [SearchController@index])->name('search.index')
Route::get('/search/suggest', [SearchController@suggest])->name('search.suggest')
Route::get('/search/data', [SearchController@search])->name('search.data')  // XHR endpoint

// Backward compatibility (redirect aliases)
Route::redirect('/database', '/search', 302)->name('database.index')
Route::redirect('/database/suggest', '/search/suggest', 302)->name('database.suggest')
Route::redirect('/database/search', '/search/data', 302)->name('database.search')
```

### Step 3: Update views
- Move all logic from database/index.blade.php into search/index.blade.php
- Remove database/index.blade.php

### Step 4: Update frontend
- Change XHR endpoint from `/database/search` to `/search/data`
- Keep credentials: 'same-origin'

## IMPLEMENTATION APPROACH
1. Create SearchController with index() + suggest() + search()
2. Update routes with canonical + redirect aliases
3. Consolidate views
4. Update frontend JavaScript
5. Test all redirects and endpoints
