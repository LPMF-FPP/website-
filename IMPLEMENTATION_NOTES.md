# Implementation Notes - Emergency Backup & WhatsApp Notifications

## STATUS: Phase 1 Backend Complete, Frontend & WhatsApp Pending

### ✅ Completed (11/30 tasks)
**Phase 1 - Job Tracking Infrastructure:**
- job_statuses table migration
- JobStatus model with helper methods
- JobStatusController for polling endpoint (`GET /api/jobs/{id}`)

**Phase 1 - Emergency Backup Backend:**
- backup_runs table migration  
- BackupRun model
- BackupService (database dump, storage archive, manifest generation)
- EmergencyBackupJob (full workflow with progress tracking)
- EmergencyBackupController (start/list/show/download)
- API routes configured
- Blade partial UI created (backup-maintenance.blade.php)
- Settings sidebar updated

### ⚠️ Pending Frontend Integration (Alpine.js)

**File**: `resources/js/pages/settings/alpine-component.js` (2910 lines)

**Required additions** to SettingsClient class:

```javascript
// Add to client state initialization
backupRunning: false,
backupProgress: 'Initializing...',
backupProgressPercent: 0,
backups: [],
backupJobId: null,
form: {
    // existing form state...
    backup: {
        retention_days: 14
    }
}

// Add methods
async startEmergencyBackup() {
    this.state.backupRunning = true;
    this.state.backupProgress = 'Starting backup...';
    try {
        const res = await fetch('/api/settings/emergency-backup', { method: 'POST' });
        const { job_id } = await res.json();
        this.state.backupJobId = job_id;
        await this.pollBackupJob(job_id);
    } catch (error) {
        this.state.error = error.message;
    } finally {
        this.state.backupRunning = false;
        await this.loadBackupList();
    }
},

async pollBackupJob(jobId, maxAttempts = 120) {
    for (let i = 0; i < maxAttempts; i++) {
        const res = await fetch(`/api/jobs/${jobId}`);
        const data = await res.json();
        
        if (data.status === 'not_found') {
            throw new Error('Job not found');
        }
        
        this.state.backupProgress = `${data.progress.current}/${data.progress.total} - ${data.progress.percentage}%`;
        this.state.backupProgressPercent = data.progress.percentage;
        
        if (data.status === 'completed') {
            this.state.backupProgress = 'Backup completed successfully';
            return data.result;
        }
        
        if (data.status === 'failed') {
            throw new Error(data.error);
        }
        
        await new Promise(resolve => setTimeout(resolve, 2000));
    }
    throw new Error('Backup timeout');
},

async loadBackupList() {
    const res = await fetch('/api/settings/emergency-backup');
    const { backups } = await res.json();
    this.state.backups = backups;
}
```

**Integration point**: Add to `init()` method:
```javascript
async init() {
    // existing init code...
    await this.loadBackupList();
}
```

### ⚠️ Remaining Backend Tasks

**Cleanup Command** (`app/Console/Commands/BackupCleanupCommand.php`):
```php
<?php
namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup {--days=14}';
    protected $description = 'Clean up old emergency backups';

    public function handle(BackupService $service): int
    {
        $days = (int) $this->option('days');
        $deleted = $service->cleanupOldBackups($days, 'emergency');
        $this->info("Deleted {$deleted} old backup(s)");
        return 0;
    }
}
```

**Settings Response Builder** (`app/Services/Settings/SettingsResponseBuilder.php`):
Add to `build()` method return array:
```php
'backup' => [
    'retention_days' => (int) Arr::get($nested, 'backup.retention_days', 14),
],
```

### 🚫 WhatsApp Feature - Not Started (14 tasks)

All WhatsApp-related tasks are pending. Implementation requires:
- 2 migrations (whatsapp_outbox table + settings data)
- 2 models (WhatsappOutbox + enums)
- 3 services (PhoneNormalizer, GowaClient, NotificationService)  
- 1 job (SendWhatsAppNotificationJob)
- 1 controller (WhatsAppSettingsController)
- Observer updates for milestone detection
- Frontend UI updates
- Settings integration

**Estimated effort**: 4-6 hours for complete implementation

## Testing Checklist

### Emergency Backup
- [ ] Run migration: `php artisan migrate`
- [ ] Start queue: `php artisan queue:listen`
- [ ] Access `/settings` page
- [ ] Click "Emergency Backup Now"
- [ ] Verify progress updates
- [ ] Check backup files created in `storage/app/backups/emergency/`
- [ ] Download DB/storage/manifest files
- [ ] Verify manifest.json integrity
- [ ] Test cleanup command: `php artisan backup:cleanup --days=7`

### Expected File Structure
```
storage/app/backups/emergency/20260109_143000/
├── db.sql.gz (database dump)
├── storage.tar.gz (storage archive)  
├── manifest.json (metadata + checksums)
└── backup.log (optional)
```

## Next Steps

1. **Integrate Alpine.js methods** (copy code blocks above into alpine-component.js)
2. **Test emergency backup flow** end-to-end
3. **Add cleanup command to scheduler** (optional: `app/Console/Kernel.php`)
4. **Decide on WhatsApp feature**: implement now or defer?

## Known Issues

- LSP diagnostics errors in `settings/index.blade.php` are pre-existing (not from this implementation)
- Alpine component size (2910 lines) makes manual editing required
- No automated tests created (add to test suite recommended)

