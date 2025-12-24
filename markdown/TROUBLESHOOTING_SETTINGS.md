# Troubleshooting: Settings Page Cannot Save

## Diagnostic Steps

### 1. Check Browser Console (MOST IMPORTANT)

1. Open http://127.0.0.1:8000/settings
2. Press `F12` to open Developer Tools
3. Go to **Console** tab
4. Click **"Simpan Pengaturan"** button
5. Look for errors in console

**Expected output:**
```
Saving settings... {numbering: {...}, branding: {...}, ...}
```

**If you see error:** Copy the full error message.

### 2. Check Laravel Logs

```bash
# Windows
type storage\logs\laravel.log | findstr "Settings update error"

# Linux/Mac
tail -f storage/logs/laravel.log | grep "Settings update error"
```

### 3. Check Network Tab

1. In Developer Tools, go to **Network** tab
2. Click "Simpan Pengaturan"
3. Find the request to `/settings/save`
4. Click on it and check:
   - **Status**: Should be `200`, if `419` = CSRF issue, if `500` = Server error
   - **Response**: See the error message
   - **Request Payload**: Verify data is sent correctly

### 4. Verify CSRF Token

In browser console, run:
```javascript
document.querySelector('meta[name=csrf-token]')?.content
```

Should output a token like: `"YjZjN2M5YzI3ZGU3..."`

If `undefined` or empty → CSRF token missing!

### 5. Test Settings Endpoint Manually

```bash
# Get CSRF token
php artisan tinker
>>> csrf_token()

# Test save (replace YOUR_TOKEN with actual token)
curl -X POST http://127.0.0.1:8000/settings/save \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: YOUR_TOKEN" \
  -H "Cookie: laravel_session=..." \
  -d '{"branding":{"lab_name":"Test"}}'
```

### 6. Check Database Permissions

```bash
php artisan tinker
>>> \App\Models\SystemSetting::first()
>>> \App\Models\SystemSetting::create(['key' => 'test', 'value' => ['foo' => 'bar']])
```

If error → Database issue!

### 7. Check User Permissions

```bash
php artisan tinker
>>> auth()->user()->can('manage-settings')
```

Should return `true`. If `false` → Permission denied!

## Common Issues & Solutions

### Issue 1: CSRF Token Mismatch (419 Error)

**Symptoms:**
- Network tab shows `419` status
- Error: "CSRF token mismatch"

**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

Then refresh browser (Ctrl+F5).

### Issue 2: Permission Denied (403 Error)

**Symptoms:**
- Network tab shows `403` status
- Error: "This action is unauthorized"

**Solution:**
```bash
# Check user role
php artisan tinker
>>> auth()->user()->role

# Update user role to admin
>>> $user = \App\Models\User::first();
>>> $user->role = 'admin';
>>> $user->save();
```

### Issue 3: JavaScript Not Loading

**Symptoms:**
- No console output when clicking "Simpan"
- Alpine.js not working

**Solution:**
```bash
# Clear view cache
php artisan view:clear

# Rebuild assets
npm run build

# Clear browser cache
# Ctrl+Shift+Del (Chrome)
```

### Issue 4: Database Error (500 Error)

**Symptoms:**
- Network tab shows `500` status
- Laravel log shows database error

**Solution:**
```bash
# Run migrations
php artisan migrate

# Check database connection
php artisan db:show
```

### Issue 5: JSON Encoding Error

**Symptoms:**
- Error: "Malformed UTF-8 characters"
- Error: "JSON error"

**Solution:**
Check for special characters in settings. Clear all settings:

```bash
php artisan tinker
>>> \App\Models\SystemSetting::truncate();
>>> \Database\Seeders\SystemSettingSeeder::run();
```

### Issue 6: `settings_flatten()` Function Not Found

**Symptoms:**
- Error: "Call to undefined function settings_flatten"

**Solution:**
```bash
composer dump-autoload
php artisan optimize:clear
```

## Quick Fix: Reset All Settings

If nothing works, reset everything:

```bash
# 1. Clear all caches
php artisan optimize:clear

# 2. Reset database
php artisan migrate:fresh --seed

# 3. Clear browser data
# Ctrl+Shift+Del → Clear cookies and cache

# 4. Restart server
# Stop: Ctrl+C
# Start: php artisan serve

# 5. Test again
# Visit: http://127.0.0.1:8000/settings
```

## Still Not Working?

### Enable Debug Mode

1. Edit `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

2. Try saving again
3. Check `storage/logs/laravel.log` for detailed errors

### Get Detailed Error

Run in browser console:
```javascript
// Enable verbose logging
localStorage.setItem('debug', 'true');

// Try save again and check console
```

### Check System Requirements

```bash
# PHP version (need 8.2+)
php -v

# Extensions
php -m | grep -E "(pdo|json|mbstring)"

# Laravel version
php artisan --version
```

## Report Issue

If still failing, provide:

1. **Browser console output** (full error)
2. **Network tab** → `/settings/save` request details
3. **Laravel log** → Last 50 lines: `tail -50 storage/logs/laravel.log`
4. **PHP version**: `php -v`
5. **Browser & OS**: e.g., "Chrome 120 on Windows 11"

Copy all information and share for further assistance.
