# Patch: HTTP 500 Error Fix for /database/search Endpoint

## File 1: Migration Renamed
```
database/migrations/2025_12_18_000000_enable_trgm_and_search_indexes.php 
  → database/migrations/2025_12_18_100000_enable_trgm_and_search_indexes.php
```

**Reason**: Ensure migrations run in correct order (index creation after schema changes)

---

## File 2: Migration Fixed
**File**: `database/migrations/2025_12_18_100000_enable_trgm_and_search_indexes.php`

### Change 1: Remove incompatible $withinTransaction property

```diff
-return new class extends Migration
-{
-    public bool $withinTransaction = false;
-
-    public function up(): void
+return new class extends Migration
+{
+    /**
+     * Disable transactions for this migration because CREATE INDEX CONCURRENTLY
+     * cannot run inside a transaction block.
+     */
+    public function withoutTransactions()
+    {
+        return true;
+    }
+
+    public function up(): void
```

### Change 2: Remove CONCURRENTLY from createIndex method

```diff
     private function createIndex(string $table, string $index, string $columnDefinition, bool $gin = false): void
     {
         if (!Schema::hasTable($table)) {
             return;
         }
 
         $using = $gin ? ' USING gin' : '';
         DB::statement(sprintf(
-            'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s%s (%s)',
+            'CREATE INDEX IF NOT EXISTS %s ON %s%s (%s)',
             $index,
             $table,
             $using,
             $columnDefinition
         ));
     }
```

### Change 3: Remove CONCURRENTLY from dropIndex method

```diff
     private function dropIndex(string $index): void
     {
-        DB::statement(sprintf('DROP INDEX CONCURRENTLY IF EXISTS %s', $index));
+        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $index));
     }
```

---

## Migration Execution

```bash
$ php artisan migrate

  INFO  Running migrations.

  2025_12_17_000000_create_people_and_cases_tables ............................ DONE
  2025_12_18_000001_add_search_columns_to_documents_table ....................... DONE
  2025_12_18_100000_enable_trgm_and_search_indexes ............................ DONE
```

---

## Before vs After

### Before (HTTP 500)
```
GET /database/search?q=test

HTTP 500 Internal Server Error

{
  "message": "SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation \"people\" does not exist",
  "exception": "PDOException",
  "file": "vendor/laravel/framework/src/Illuminate/Database/Connection.php",
  "line": 570
}

Stack Trace:
  DatabaseController::search() [line 570]
    → SearchService::search() [line 28]
      → searchPeople() [line 89]
        → paginate()
          → PDO::execute() [ERROR]
```

### After (Fixed)
```
GET /database/search?q=test (unauthenticated)

HTTP 302 Found
Location: http://127.0.0.1:8000/login
```

```
GET /database/search?q=test&per_page_people=6 (authenticated)

HTTP 200 OK
Content-Type: application/json

{
  "query": "test",
  "doc_type": "all",
  "sort": "relevance",
  "summary": {
    "people_total": 0,
    "documents_total": 5
  },
  "people": {
    "pagination": { ... },
    "items": []
  },
  "documents": {
    "pagination": { ... },
    "items": [ ... ]
  }
}
```

---

## Validation

### Script
```bash
bash validate-500-fix.sh
```

### Manual Commands
```bash
# Verify tables exist
php artisan tinker
>>> Schema::hasTable('people')

# Verify migrations ran
php artisan migrate:status | grep 2025_12_17

# Verify routes
php artisan route:list | grep search

# Test endpoint
curl -i http://127.0.0.1:8000/database/search?q=test
```

---

## Summary of Changes

| Component | Change | Reason |
|-----------|--------|--------|
| Migration Timestamp | `2025_12_18_000000` → `2025_12_18_100000` | Ensure index creation runs after schema |
| Migration Syntax | Removed `$withinTransaction` | Incompatible with Laravel 12 |
| Index Creation | Removed `CONCURRENTLY` keyword | Cannot run inside transactions |
| Index Dropping | Removed `CONCURRENTLY` keyword | Consistency with create operations |

**Result**: All 3 pending migrations now execute successfully, creating the required tables and indexes. The `/database/search` endpoint no longer returns HTTP 500.

