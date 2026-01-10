# Production-Readiness Improvements

## Installation & Setup

### Quick Start Commands

```bash
# Clear config cache
php artisan config:clear && php artisan cache:clear

# Verify health endpoints
php artisan route:list --path=health

# Run tests
php artisan test
```

### Environment Variables

Add these to your `.env`:

```env
# Observability
DB_SLOW_QUERY_THRESHOLD_MS=1000

# Error Tracking
SENTRY_LARAVEL_DSN=https://your-sentry-dsn@sentry.io/project-id
SENTRY_TRACES_SAMPLE_RATE=0.1

# Slack Error Alerting
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
LOG_SLACK_USERNAME="LIMS Production"
LOG_SLACK_EMOJI=":rotating_light:"
LOG_SLACK_LEVEL=critical

# Laravel Telescope (dev/staging only)
TELESCOPE_ENABLED=false

# Laravel Pulse
PULSE_ENABLED=true
PULSE_INGEST_DRIVER=database

# WhatsApp Service Timeouts
WHATSAPP_GOWA_TIMEOUT=30
WHATSAPP_GOWA_RETRY_TIMES=3
WHATSAPP_GOWA_RETRY_SLEEP=1000

# AWS S3 Timeouts
AWS_S3_TIMEOUT=60
AWS_S3_CONNECT_TIMEOUT=10
```

## Implemented Features

### 1. Enhanced Health Checks ✅

**Endpoints:**

- `/health` - Full health check (database, cache, queue, storage)
- `/health/liveness` - Basic app responsiveness
- `/health/readiness` - Database connectivity

**Usage:**

```bash
curl http://localhost:8000/health
curl http://localhost:8000/health/liveness
curl http://localhost:8000/health/readiness
```

**Expected Response:**

```json
{
    "status": "healthy",
    "timestamp": "2026-01-10T20:48:00.000000Z",
    "app": "Laravel",
    "environment": "production",
    "commit": null,
    "checks": {
        "database": {
            "status": "healthy",
            "response_time_ms": 12.34,
            "message": "Database connection successful"
        },
        "cache": {
            "status": "healthy",
            "response_time_ms": 5.67,
            "driver": "file"
        },
        "queue": {
            "status": "healthy",
            "driver": "database",
            "pending_jobs": 0
        },
        "storage": {
            "status": "healthy",
            "response_time_ms": 8.9,
            "driver": "local"
        }
    }
}
```

### 2. Query Performance Monitoring ✅

Automatically logs slow queries in production:

- **Warning**: Queries > 1000ms
- **Error**: Queries > 3000ms

**Configuration:**

```env
DB_SLOW_QUERY_THRESHOLD_MS=1000
```

**Log Output:**

```
[2026-01-10 20:48:00] production.WARNING: Slow query detected {"sql":"SELECT * FROM...","time_ms":1234,"bindings":[...],"connection":"pgsql"}
```

### 3. Improved Exception Handling ✅

- **Sentry/Flare Integration**: Auto-report exceptions in production
- **Exception Throttling**: Rate-limit error reporting (5/minute per exception type)
- **Smart Filtering**: Don't report validation/auth exceptions

**Setup Sentry:**

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

### 4. PII Encryption ✅

Sensitive data automatically encrypted at rest:

**Encrypted Fields:**

- `TestRequest::suspect_name`
- `TestRequest::suspect_address`

**Implementation:**

```php
protected $casts = [
    'suspect_name' => 'encrypted',
    'suspect_address' => 'encrypted',
];
```

### 5. API Rate Limiting ✅

All API routes rate-limited to 60 requests/minute per user.

**Override for specific routes:**

```php
Route::middleware(['throttle:120,1'])->group(function () {
    // Your routes
});
```

### 6. Service Timeout Configurations ✅

Configurable timeouts prevent hanging requests:

- **WhatsApp GOWA**: 30s timeout, 3 retries
- **AWS S3**: 60s timeout, 10s connection timeout

### 7. Enhanced Job Retry Logic ✅

`SendWhatsAppNotificationJob` implements exponential backoff:

```php
public function backoff(): array
{
    return [60, 120, 240, 480, 960]; // seconds
}
```

## Advanced Monitoring (Installed)

### Laravel Telescope (Dev/Staging) ✅

**Access:** `/telescope` (admin/supervisor only)

**Features:**

- Request/response inspection
- Query debugging
- Exception tracking
- Job monitoring
- Cache operations
- Log viewing

**Usage:**

```bash
# Access dashboard (local/staging)
http://localhost:8000/telescope

# Authorization: admin and supervisor roles only
```

**Configuration:** `config/telescope.php`, `app/Providers/TelescopeServiceProvider.php`

### Laravel Pulse (Production Metrics) ✅

**Access:** `/pulse` (admin/supervisor only)

**Features:**

- Real-time application metrics
- Slow queries tracking
- Exception monitoring
- Job performance
- Server metrics
- Cache hit rates

**Usage:**

```bash
# Run Pulse worker in production
php artisan pulse:work

# Access dashboard
http://localhost:8000/pulse

# Check Pulse status
php artisan pulse:check
```

**Configuration:** `config/pulse.php`

**Recorders Enabled:**

- Cache interactions
- Exceptions
- Queues
- Slow jobs (threshold: 1000ms)
- Slow queries (threshold: 1000ms)
- Slow outgoing requests (threshold: 1000ms)
- Server metrics
- User requests
- User jobs

### Sentry Error Tracking ✅

**Setup:**

1. Create account at https://sentry.io
2. Get your DSN from project settings
3. Add to `.env`:

```env
SENTRY_LARAVEL_DSN=https://your-key@sentry.io/your-project-id
SENTRY_TRACES_SAMPLE_RATE=0.1
```

4. Test:

```bash
php artisan tinker
>>> throw new \Exception('Test Sentry');
```

**Configuration:** `config/sentry.php`, `bootstrap/app.php`

### Slack Alerting ✅

Critical errors automatically sent to Slack.

**Setup:**

1. Create Slack webhook at https://api.slack.com/messaging/webhooks
2. Add to `.env`:

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
LOG_SLACK_USERNAME="LIMS Production"
LOG_SLACK_EMOJI=":rotating_light:"
LOG_SLACK_LEVEL=critical
```

3. Update `LOG_STACK` in `.env`:

```env
LOG_STACK=single,slack
```

**Configuration:** `config/logging.php`

## Monitoring Checklist

### Daily

- [ ] Check `/health` endpoint
- [ ] Review `/pulse` dashboard metrics
- [ ] Check Slack for critical error notifications
- [ ] Review error logs for critical issues
- [ ] Monitor slow query logs in Pulse

### Weekly

- [ ] Review Sentry error trends
- [ ] Check `/telescope` for debugging insights (dev/staging)
- [ ] Check queue health: `php artisan queue:monitor`
- [ ] Verify backup success
- [ ] Review Pulse job performance metrics

### Monthly

- [ ] Review security advisories
- [ ] Update dependencies
- [ ] Performance audit using Pulse metrics
- [ ] Review and clean up old Pulse/Telescope data

## Troubleshooting

### Health Check Fails

```bash
# Check database connection
php artisan db:monitor

# Check queue status
php artisan queue:monitor

# Clear cache
php artisan config:clear && php artisan cache:clear
```

### Slow Queries

1. Review logs: `storage/logs/laravel.log`
2. Identify N+1 queries
3. Add eager loading or indexes

### Error Tracking Not Working

1. Verify Sentry DSN: `php artisan tinker` → `config('sentry.dsn')`
2. Check error logs for Sentry errors
3. Test manually: `php artisan tinker` → `throw new \Exception('Test Sentry');`

### Telescope/Pulse Not Accessible

1. Check authorization gates in `app/Providers/AppServiceProvider.php` and `app/Providers/TelescopeServiceProvider.php`
2. Verify user role is `admin` or `supervisor`
3. Clear config cache: `php artisan config:clear`
4. Check routes: `php artisan route:list | grep telescope` or `php artisan route:list | grep pulse`

### Pulse Not Recording Metrics

1. Check if Pulse is enabled: `config('pulse.enabled')`
2. Run Pulse worker: `php artisan pulse:work`
3. Check database tables exist: `pulse_*` tables
4. Verify recorders are enabled in `config/pulse.php`

## Files Modified

### Initial Production-Ready Implementation (v1.2.4)

- `app/Http/Controllers/HealthController.php` - Enhanced health checks
- `app/Providers/AppServiceProvider.php` - Query monitoring + Pulse authorization
- `bootstrap/app.php` - Exception handling
- `config/database.php` - Slow query threshold
- `config/services.php` - Service timeouts & monitoring
- `routes/web.php` - Health endpoints
- `routes/api.php` - Rate limiting
- `.env.example` - Configuration templates
- `app/Models/TestRequest.php` - PII encryption

### Advanced Monitoring Tools Installation (v1.2.5)

- `composer.json` / `composer.lock` - Added laravel/telescope, laravel/pulse, sentry/sentry-laravel
- `config/telescope.php` - Telescope configuration
- `config/pulse.php` - Pulse configuration
- `config/sentry.php` - Sentry configuration
- `config/logging.php` - Slack channel configuration
- `app/Providers/TelescopeServiceProvider.php` - Telescope authorization
- `app/Providers/AppServiceProvider.php` - viewPulse gate
- `.env.example` - Added Telescope, Pulse, Sentry, Slack configuration
- `database/migrations/*_create_telescope_entries_table.php` - Telescope database
- `database/migrations/*_create_pulse_tables.php` - Pulse database
- `resources/views/vendor/pulse/dashboard.blade.php` - Pulse dashboard

## Documentation

See `WALKTHROUGH.md` for complete implementation details and version history.
