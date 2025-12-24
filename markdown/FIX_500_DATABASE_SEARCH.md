# Fix: HTTP 500 Error on /database/search Endpoint

## Problem Summary

The `/database/search` XHR endpoint was returning **HTTP 500 Internal Server Error** with the following database error:

```
SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "people" does not exist
```

### Root Cause

The `SearchService::searchPeople()` method (line 89) attempts to paginate query results from a `people` table that did not exist in the database. The migration `2025_12_17_000000_create_people_and_cases_tables.php` was pending and had never been executed.

### Stack Trace
```
DatabaseController::search() [line 570]
  → SearchService::search() [line 28]
    → searchPeople() [line 89]
      → paginate()
        → PDO::execute() [SQLSTATE[42P01]]
```

## Solution Implemented

### 1. Database Migrations Fixed

#### Issue 1: Incompatible Migration Syntax
**File**: `database/migrations/2025_12_18_000000_enable_trgm_and_search_indexes.php`

**Problem**: Migration used deprecated `public bool $withinTransaction = false` syntax incompatible with Laravel 12

**Fix**: Removed the incompatible property declaration

#### Issue 2: CONCURRENTLY Index Creation in Transaction
**File**: `database/migrations/2025_12_18_000000_enable_trgm_and_search_indexes.php`

**Problem**: `CREATE INDEX CONCURRENTLY` cannot run inside a transaction block (PostgreSQL limitation)

**Fix**: Removed `CONCURRENTLY` keyword from both `createIndex()` and `dropIndex()` methods since:
- The `IF NOT EXISTS` clause prevents duplicate index errors
- Running inside a transaction is acceptable during migrations
- CONCURRENTLY is an optimization for production but not required

#### Issue 3: Migration Execution Order
**Problem**: Timestamp collision - both migrations named with `2025_12_18_000000` and `2025_12_18_000001`, but index creation migration attempted to create indexes on columns that didn't exist yet

**Solution**: Renamed index migration file from `2025_12_18_000000_enable_trgm_and_search_indexes.php` to `2025_12_18_100000_enable_trgm_and_search_indexes.php` to ensure it runs after schema modifications

### 2. Migrations Executed

```bash
php artisan migrate
```

**Migrations applied**:
- `2025_12_17_000000_create_people_and_cases_tables.php` - Creates people, cases, and case_people tables
- `2025_12_18_000001_add_search_columns_to_documents_table.php` - Adds search-related columns (title, ba_no, lp_no, doc_date)
- `2025_12_18_100000_enable_trgm_and_search_indexes.php` - Creates trgm indexes for full-text search

## Tables Created

### people
```sql
- id (bigint, auto-increment)
- name (text)
- role (text, nullable)
- photo_path (text, nullable)
- created_at (timestamp with timezone, nullable)
```

### cases
```sql
- id (bigint, auto-increment)
- title (text)
- lp_no (text)
- created_at (timestamp with timezone, nullable)
```

### case_people
```sql
- case_id (unsigned bigint)
- person_id (unsigned bigint)
- role_in_case (text, nullable)
```

### documents (modified)
Added columns:
- doc_type (string, nullable)
- ba_no (string, nullable)
- title (string, nullable)
- lp_no (string, nullable)
- doc_date (date, nullable)

## Search Endpoint Routes

### Current Configuration
```
GET /search                 → Route::view() renders search.index template
GET /database/search        → DatabaseController@search() JSON endpoint
GET /api/search             → Api\SearchController (API guard, Bearer token)
```

### Middleware Chain
All web search routes protected by:
- `auth` - Requires authentication
- `verified` - Requires verified email
- `can:view-database` - Authorization gate

## Validation

### Database Schema Verification
```bash
php artisan tinker
>>> Schema::hasTable('people')  # Returns true
>>> Schema::hasTable('cases')   # Returns true  
>>> Schema::hasTable('case_people')  # Returns true
```

### HTTP Endpoint Tests

#### 1. Unauthenticated Request (Expected: 302 Redirect to Login)
```bash
curl -i "http://127.0.0.1:8000/database/search?q=test"
# Response: HTTP 302 Found → /login
```

#### 2. Authenticated Request (Expected: 200 OK with JSON)
```bash
# First login and get session cookie
curl -s -c cookies.txt -d "email=user@example.com&password=password" \
  "http://127.0.0.1:8000/login"

# Then make search request
curl -i -b cookies.txt "http://127.0.0.1:8000/database/search?q=test&per_page_people=6"
# Response: HTTP 200 OK (JSON)
```

#### 3. Route Verification
```bash
php artisan route:list | grep search
```

Expected output:
```
GET|HEAD  api/search ..................... api.search › Api\SearchController
GET|HEAD  database/search ................ database.search › DatabaseController@search
GET|HEAD  search ......................... search
```

## Files Modified

### Database Migrations
1. `database/migrations/2025_12_18_000000_enable_trgm_and_search_indexes.php` (fixed)
   - Renamed to `2025_12_18_100000_enable_trgm_and_search_indexes.php`
   - Removed incompatible `$withinTransaction` property
   - Removed CONCURRENTLY keyword from index operations

2. `database/migrations/2025_12_17_000000_create_people_and_cases_tables.php`
   - No changes (pre-existing, was pending)

3. `database/migrations/2025_12_18_000001_add_search_columns_to_documents_table.php`
   - No changes (pre-existing, now runs in correct order)

## No Code Changes Required

**Good News**: No application code changes were needed! The fix was purely:
1. Database schema - executing pending migrations
2. Migration compatibility - fixing broken migration syntax
3. Execution order - ensuring migrations run in dependency order

The existing `SearchService`, `DatabaseController`, and frontend components work correctly once the database schema is properly initialized.

## Frontend Impact

The frontend search component in `resources/views/search/index.blade.php` already calls:
```javascript
/database/search  // Legacy XHR endpoint (still works)
```

This endpoint now functions correctly and returns:
- HTTP 200 with JSON response (authenticated users)
- HTTP 302 redirect to login (unauthenticated)

## Next Steps: Route Consolidation (Optional)

**Not implemented yet**, but recommended:
1. Create canonical endpoint `/search/data` with shared logic
2. Make `/database/search` an alias/redirect to canonical
3. Update frontend to use canonical endpoint
4. Maintain backward compatibility

See [CONSOLIDATION_PLAN.md](./patcher/CONSOLIDATION_PLAN.md) for consolidation strategy.

## Validation Checklist

- ✅ `people` table created and has correct schema
- ✅ `cases` table created and has correct schema
- ✅ `case_people` junction table created
- ✅ `documents` table extended with search columns
- ✅ Search indexes created successfully
- ✅ `/database/search` endpoint no longer returns 500
- ✅ Unauthenticated requests redirect to login (302)
- ✅ Database queries execute without errors
- ✅ All migrations in correct execution order
- ✅ No 500 errors in laravel.log

## Rollback Instructions

If needed, rollback to previous state:
```bash
php artisan migrate:rollback
```

This will:
1. Drop all tables created by pending migrations
2. Restore database to last stable state
3. Prevent any 500 errors related to undefined tables

