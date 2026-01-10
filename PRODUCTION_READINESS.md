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

# Error Tracking (choose one)
SENTRY_LARAVEL_DSN=https://your-sentry-dsn
SENTRY_TRACES_SAMPLE_RATE=0.1
# OR
FLARE_KEY=your-flare-key

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

## Next Steps (Optional)

### Install Laravel Telescope (Dev/Staging)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Install Laravel Pulse (Production Metrics)

```bash
composer require laravel/pulse
php artisan pulse:install
php artisan migrate
```

### Setup Alerting

Configure Slack webhook for critical errors:

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

## Monitoring Checklist

### Daily

- [ ] Check `/health` endpoint
- [ ] Review error logs for critical issues
- [ ] Monitor slow query logs

### Weekly

- [ ] Review Sentry/Flare error trends
- [ ] Check queue health: `php artisan queue:monitor`
- [ ] Verify backup success

### Monthly

- [ ] Review security advisories
- [ ] Update dependencies
- [ ] Performance audit

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

1. Verify Sentry DSN: `php artisan tinker` → `config('services.monitoring.sentry.dsn')`
2. Check error logs for Sentry errors
3. Test manually: `throw new \Exception('Test Sentry');`

## Files Modified

- `app/Http/Controllers/HealthController.php` - Enhanced health checks
- `app/Providers/AppServiceProvider.php` - Query monitoring
- `bootstrap/app.php` - Exception handling
- `config/database.php` - Slow query threshold
- `config/services.php` - Service timeouts & monitoring
- `routes/web.php` - Health endpoints
- `routes/api.php` - Rate limiting
- `.env.example` - Configuration templates
- `app/Models/TestRequest.php` - PII encryption

## Documentation

See `WALKTHROUGH.md` for complete implementation details and version history.
