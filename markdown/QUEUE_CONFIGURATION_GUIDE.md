# Queue Configuration Guide

## Overview

Aplikasi Laravel ini menggunakan **database queue driver** untuk menangani background jobs dan event listeners yang di-queue (seperti notifikasi saat issue number).

## Queue Tables

Aplikasi membutuhkan 3 tabel untuk queue database:

1. **`jobs`** - Menyimpan antrian job yang pending
2. **`job_batches`** - Menyimpan batch job information
3. **`failed_jobs`** - Menyimpan job yang gagal untuk retry/debugging

## Setup untuk Local Development

### Option A: Database Queue (Recommended untuk testing async)

**Konfigurasi:**
```env
QUEUE_CONNECTION=database
```

**Migration:**
```bash
# Migration sudah tersedia di database/migrations/
# Jalankan migration untuk membuat tabel:
php artisan migrate

# Verify tabel sudah ada:
php artisan tinker
>>> DB::table('jobs')->count();  // Should return 0 (no error)
```

**Menjalankan Queue Worker:**
```bash
# Di terminal terpisah, jalankan queue worker:
php artisan queue:work

# Atau untuk development (auto-reload saat code berubah):
php artisan queue:listen
```

**Kapan menggunakan:**
- Ingin test async behavior (jobs tidak block request)
- Ingin test retry mechanism untuk failed jobs
- Development yang mirip production

### Option B: Sync Queue (Simplest untuk dev)

**Konfigurasi:**
```env
QUEUE_CONNECTION=sync
```

**Behavior:**
- Job dijalankan **segera** dalam request yang sama (tidak async)
- Tidak perlu queue worker
- Tidak perlu tabel `jobs`

**Kapan menggunakan:**
- Development sederhana tanpa perlu async
- Debugging (mudah trace error dalam request)
- Testing tanpa kompleksitas queue worker

**Cara switch:**
1. Update `.env`: `QUEUE_CONNECTION=sync`
2. Restart server: `php artisan serve`
3. Tidak perlu queue worker

## Production Recommendations

Untuk production, gunakan:
- **Redis queue** (lebih cepat, scalable)
- **Supervisor** untuk menjaga queue worker tetap running

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Event yang Menggunakan Queue

### NumberIssued Event
**Listener:** `App\Listeners\SendIssueNotification`  
**Trigger:** Saat `NumberingService::issue()` generate nomor baru  
**Action:** Kirim notifikasi via log (bisa diubah ke email/slack)

**File:** [app/Listeners/SendIssueNotification.php](app/Listeners/SendIssueNotification.php)

```php
class SendIssueNotification implements ShouldQueue
{
    public function handle(NumberIssued $event): void
    {
        // Currently logs to laravel.log
        // Can be extended to send email/slack notifications
    }
}
```

## Troubleshooting

### Error: `relation "jobs" does not exist`

**Cause:** Migration untuk tabel `jobs` belum dijalankan

**Solution:**
```bash
php artisan migrate
```

### Error: Queue worker tidak memproses jobs

**Check:**
1. Apakah queue worker running? `ps aux | grep queue:work`
2. Apakah ada job di tabel? `DB::table('jobs')->count()`
3. Apakah connection benar? Check `.env` QUEUE_CONNECTION

**Restart worker:**
```bash
# Kill existing worker
pkill -f "queue:work"

# Start new worker
php artisan queue:work --tries=3 --timeout=90
```

### Jobs stuck di queue

**Check failed jobs:**
```bash
php artisan queue:failed

# Retry failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Flush all failed jobs
php artisan queue:flush
```

## Monitoring Queue

### Check Queue Status
```bash
# Count pending jobs
php artisan tinker
>>> DB::table('jobs')->count();

# Count failed jobs
>>> DB::table('failed_jobs')->count();

# See failed jobs with details
php artisan queue:failed
```

### Clear Queue
```bash
# Clear all jobs from queue (caution!)
php artisan queue:clear

# Or via SQL:
php artisan tinker
>>> DB::table('jobs')->truncate();
```

## Health Check

Aplikasi secara otomatis akan memverifikasi keberadaan tabel queue saat menggunakan database driver. Jika tabel tidak ada, error akan jelas menunjukkan bahwa migration perlu dijalankan.

**Manual Check:**
```bash
php artisan tinker
>>> Schema::hasTable('jobs')        // Should return true
>>> Schema::hasTable('job_batches')  // Should return true
>>> Schema::hasTable('failed_jobs')  // Should return true
```

## Configuration Files

- **`.env`** - Set `QUEUE_CONNECTION`
- **`config/queue.php`** - Queue driver configuration
- **`database/migrations/2025_12_19_042206_create_jobs_table.php`** - Jobs table migration
- **`database/migrations/2025_12_19_042206_create_job_batches_table.php`** - Job batches migration
- **`database/migrations/2025_09_29_050049_create_failed_jobs_table.php`** - Failed jobs migration

## Quick Start Checklist

- [x] Set `QUEUE_CONNECTION` in `.env` (database or sync)
- [x] Run migrations: `php artisan migrate`
- [ ] If using database queue, start worker: `php artisan queue:work`
- [ ] Test: Create sample/request → check logs for notification
- [ ] Monitor: Check `storage/logs/laravel.log` for queue activity

## Notes

- **Local dev:** `sync` driver simplest (no worker needed)
- **Testing async:** `database` driver with worker
- **Production:** Consider `redis` driver with Supervisor
- **Failed jobs:** Check `failed_jobs` table for debugging
- **Logs:** Queue activity logged to `storage/logs/laravel.log`
