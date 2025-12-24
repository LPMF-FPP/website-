# PostgreSQL Setup Guide

Aplikasi ini **fully compatible** dengan PostgreSQL. Panduan ini akan membantu Anda migrasi dari SQLite/MySQL ke PostgreSQL.

## Mengapa PostgreSQL?

✅ **Better JSON/JSONB support** - Aplikasi ini menggunakan banyak JSON columns  
✅ **Better full-text search** - Untuk fitur search di database  
✅ **More robust transactions** - Better handling untuk concurrent requests  
✅ **Better performance** - Terutama untuk complex queries dan aggregations  
✅ **Production-ready** - Lebih stabil untuk production environment  

## Prerequisites

### Windows

**Option 1: PostgreSQL Official Installer**
1. Download dari https://www.postgresql.org/download/windows/
2. Install PostgreSQL (pilih versi 14+)
3. Set password untuk user `postgres`
4. Default port: `5432`

**Option 2: Via Docker (Recommended)**
```powershell
docker run -d `
  --name postgres-pusdokkes `
  -e POSTGRES_DB=pusdokkes_subunit `
  -e POSTGRES_USER=postgres `
  -e POSTGRES_PASSWORD=secret `
  -p 5432:5432 `
  postgres:16-alpine
```

### Linux/Mac

```bash
# Ubuntu/Debian
sudo apt install postgresql postgresql-contrib

# macOS (Homebrew)
brew install postgresql@16

# Docker (All platforms)
docker run -d \
  --name postgres-pusdokkes \
  -e POSTGRES_DB=pusdokkes_subunit \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=secret \
  -p 5432:5432 \
  postgres:16-alpine
```

## Setup Steps

### 1. Install PHP PostgreSQL Extension

```bash
# Check if already installed
php -m | grep pgsql

# If not installed:
# Ubuntu/Debian
sudo apt install php-pgsql

# Windows (uncomment di php.ini)
extension=pgsql
extension=pdo_pgsql
```

Restart web server setelah enable extension.

### 2. Create Database

**Via psql CLI:**
```bash
# Login ke PostgreSQL
psql -U postgres -h localhost

# Create database
CREATE DATABASE pusdokkes_subunit;

# Create user (optional, jika tidak pakai user postgres)
CREATE USER pusdokkes WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE pusdokkes_subunit TO pusdokkes;

# Exit
\q
```

**Via Docker exec:**
```bash
docker exec -it postgres-pusdokkes psql -U postgres -c "CREATE DATABASE pusdokkes_subunit;"
```

**Via GUI Tools:**
- pgAdmin: https://www.pgadmin.org/
- DBeaver: https://dbeaver.io/
- TablePlus: https://tableplus.com/

### 3. Update Laravel Configuration

Edit file `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pusdokkes_subunit
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### 4. Clear Config Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### 5. Test Connection

```bash
php artisan db:show

# Should display:
# PostgreSQL 16.x
# Database: pusdokkes_subunit
```

### 6. Run Migrations

```bash
# Fresh install
php artisan migrate:fresh --seed

# Or migrate from existing
php artisan migrate
```

### 7. Verify Setup

```bash
# Check tables
php artisan db:table users

# Test query
php artisan tinker
>>> \App\Models\User::count()
```

## Migration dari Database Lain

### Option 1: Fresh Install (Recommended)

```bash
# Backup data lama (if needed)
php artisan db:seed --class=BackupSeeder

# Update .env ke PostgreSQL
# Run migrations
php artisan migrate:fresh --seed
```

### Option 2: Export-Import Data

**From SQLite:**
```bash
# Export dari SQLite
sqlite3 database/database.sqlite .dump > backup.sql

# Import ke PostgreSQL (manual conversion needed)
# Atau gunakan tools seperti pgloader
```

**From MySQL:**
```bash
# Export dari MySQL
mysqldump -u root -p database_name > backup.sql

# Convert dan import ke PostgreSQL
# Gunakan tools: pgloader, mysql2postgresql, atau manual
```

### Option 3: Using pgloader (Automatic)

```bash
# Install pgloader
# Ubuntu: sudo apt install pgloader

# From MySQL
pgloader mysql://user:pass@localhost/old_db \
         postgresql://postgres:secret@localhost/pusdokkes_subunit

# From SQLite
pgloader database/database.sqlite \
         postgresql://postgres:secret@localhost/pusdokkes_subunit
```

## PostgreSQL-Specific Optimizations

### 1. JSON Performance

PostgreSQL menggunakan JSONB yang jauh lebih cepat:

```php
// Indexing JSON columns untuk search cepat
Schema::table('system_settings', function (Blueprint $table) {
    $table->index('value'); // JSONB index
});

// Query optimization
SystemSetting::whereJsonContains('value->roles', 'admin')->get();
```

### 2. Full-Text Search

```sql
-- Create full-text search index
CREATE INDEX idx_search_content ON test_requests 
USING gin(to_tsvector('indonesian', 
  coalesce(suspect_name,'') || ' ' || 
  coalesce(case_description,'')
));

-- Usage in Laravel
TestRequest::whereRaw(
    "to_tsvector('indonesian', suspect_name || ' ' || case_description) @@ plainto_tsquery('indonesian', ?)",
    [$searchTerm]
)->get();
```

### 3. Connection Pool (Production)

Di `.env` untuk production:

```env
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=pusdokkes_subunit
DB_USERNAME=pusdokkes_user
DB_PASSWORD=strong_password

# Connection pool settings
DB_POOL_MIN=2
DB_POOL_MAX=20
```

## Troubleshooting

### Error: "could not find driver"

**Solution:** Install PHP PostgreSQL extension
```bash
sudo apt install php-pgsql
# atau uncomment di php.ini: extension=pdo_pgsql
```

### Error: "SQLSTATE[08006] Connection refused"

**Solution:** 
1. Pastikan PostgreSQL running: `sudo systemctl status postgresql`
2. Check port: `sudo netstat -plnt | grep 5432`
3. Allow connections di `postgresql.conf`: `listen_addresses = '*'`
4. Allow authentication di `pg_hba.conf`: `host all all 127.0.0.1/32 md5`

### Error: "FATAL: database does not exist"

**Solution:** Create database dulu:
```bash
psql -U postgres -c "CREATE DATABASE pusdokkes_subunit;"
```

### Performance Issues

**Solution:** Optimize PostgreSQL config di `postgresql.conf`:
```ini
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
work_mem = 16MB
```

Restart PostgreSQL setelah config change.

## Production Deployment

### Best Practices

1. **Use connection pooler** (PgBouncer)
2. **Enable SSL** untuk koneksi aman
3. **Setup automated backups**
4. **Monitor slow queries**
5. **Regular VACUUM and ANALYZE**

### Backup Strategy

```bash
# Manual backup
pg_dump -U postgres pusdokkes_subunit > backup_$(date +%Y%m%d).sql

# Restore
psql -U postgres pusdokkes_subunit < backup_20250109.sql

# Automated daily backup (cron)
0 2 * * * pg_dump -U postgres pusdokkes_subunit | gzip > /backups/db_$(date +\%Y\%m\%d).sql.gz
```

### Cloud PostgreSQL Services

- **AWS RDS** - https://aws.amazon.com/rds/postgresql/
- **Google Cloud SQL** - https://cloud.google.com/sql/postgresql
- **Azure Database** - https://azure.microsoft.com/en-us/products/postgresql
- **DigitalOcean Managed** - https://www.digitalocean.com/products/managed-databases-postgresql
- **Supabase** - https://supabase.com/ (Free tier available)

## Performance Comparison

| Feature | SQLite | MySQL | PostgreSQL |
|---------|--------|-------|------------|
| JSON Support | Basic | Good | **Excellent (JSONB)** |
| Full-text Search | Limited | Good | **Excellent** |
| Concurrent Writes | Poor | Good | **Excellent** |
| ACID Compliance | Good | Good | **Excellent** |
| Production Ready | No | Yes | **Yes** |
| Best for | Development | Web Apps | **Enterprise Apps** |

## Kesimpulan

✅ **Untuk Development**: SQLite sudah cukup  
✅ **Untuk Production**: **PostgreSQL strongly recommended**  
✅ **Migration**: Mudah, tinggal update `.env` dan run migrations  

Aplikasi ini **100% compatible** dengan PostgreSQL tanpa perlu modifikasi code!
