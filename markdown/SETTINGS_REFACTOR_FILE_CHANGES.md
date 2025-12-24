# Settings Backend Refactoring - File Changes

## 1. SettingsRepository.php (NEW)
**Path**: `app/Repositories/SettingsRepository.php`

**Purpose**: Abstraksi untuk settings storage, memisahkan data access dari business logic

**Key Methods**:
- `all()`: Get all settings
- `get($key, $default)`: Get single setting
- `put($key, $value, $userId)`: Store/update setting
- `putMany($settings, $userId)`: Store multiple
- `forget($key)`: Delete setting
- `prefix($prefix)`: Get settings by key prefix

**Usage**:
```php
$repo = app(SettingsRepository::class);
$timezone = $repo->get('locale.timezone', 'Asia/Jakarta');
$repo->put('locale.timezone', 'Asia/Makassar', auth()->id());
```

---

## 2. SettingsWriter.php (MODIFIED)
**Path**: `app/Services/Settings/SettingsWriter.php`

**Changes**:
- Added `SettingsRepository` dependency injection
- Replaced direct `SystemSetting` model access with repository calls
- `SystemSetting::where()->first()` → `$this->repository->get()`
- `SystemSetting::updateOrCreate()` → `$this->repository->put()`
- `SystemSetting::delete()` → `$this->repository->forget()`

**Benefit**: Cleaner separation, easier to test, can swap storage implementation

---

## 3. NumberingSettingsRequest.php (MODIFIED)
**Path**: `app/Http/Requests/Settings/NumberingSettingsRequest.php`

**Changes Added**:
```php
protected function prepareForValidation(): void
{
    if ($this->has('numbering')) {
        $numbering = $this->input('numbering', []);
        
        foreach ($numbering as $scope => $config) {
            // Convert empty string to null
            if (isset($config['reset']) && $config['reset'] === '') {
                $numbering[$scope]['reset'] = null;
            }
            if (isset($config['start_from']) && $config['start_from'] === '') {
                $numbering[$scope]['start_from'] = null;
            }
            // Trim pattern
            if (isset($config['pattern'])) {
                $numbering[$scope]['pattern'] = trim($config['pattern']);
            }
        }
        
        $this->merge(['numbering' => $numbering]);
    }
}
```

**Rules Changed**:
- `'numbering' => ['required', ...]` → `['sometimes', 'required', ...]`
- `'numbering.*.reset' => ['nullable', ...]` → `['sometimes', 'nullable', ...]`
- etc.

**Benefit**: Partial updates tidak error 422, empty strings → null

---

## 4. BrandingSettingsRequest.php (MODIFIED)
**Path**: `app/Http/Requests/Settings/BrandingSettingsRequest.php`

**Changes Added**:
```php
protected function prepareForValidation(): void
{
    // Trim branding fields
    if ($this->has('branding')) {
        $branding = $this->input('branding', []);
        foreach (['lab_code', 'org_name', ...] as $field) {
            if (isset($branding[$field])) {
                $branding[$field] = trim($branding[$field]);
                // Empty → null for nullable fields
                if ($branding[$field] === '' && in_array($field, [...])) {
                    $branding[$field] = null;
                }
            }
        }
        $this->merge(['branding' => $branding]);
    }
    
    // Trim PDF fields recursively
    if ($this->has('pdf')) {
        $pdf = $this->trimRecursive($this->input('pdf', []));
        $this->merge(['pdf' => $pdf]);
    }
}

private function trimRecursive(array $data): array { ... }
```

**Rules Changed**: All required rules → `sometimes|required`, support partial updates

**Benefit**: Konsisten normalisasi, partial updates supported

---

## 5. LocalizationSettingsRequest.php (ALREADY GOOD)
**Path**: `app/Http/Requests/Settings/LocalizationSettingsRequest.php`

**Existing Features** (unchanged):
- ✅ `prepareForValidation()` already present
- ✅ `sometimes` rules for partial updates
- ✅ Storage path validation (no absolute, no `..`)
- ✅ Empty string → null conversion

**Note**: Already meets requirements, no changes needed

---

## 6. NotificationsSecurityRequest.php (MODIFIED)
**Path**: `app/Http/Requests/Settings/NotificationsSecurityRequest.php`

**Changes Added**:
```php
protected function prepareForValidation(): void
{
    if ($this->has('notifications')) {
        $notifications = $this->input('notifications', []);
        
        // Trim email fields
        if (isset($notifications['email'])) {
            foreach (['default_recipient', 'subject', 'body'] as $field) {
                if (isset($notifications['email'][$field])) {
                    $value = trim($notifications['email'][$field]);
                    $notifications['email'][$field] = $value === '' ? null : $value;
                }
            }
        }
        
        // Trim WhatsApp fields
        // ... similar
        
        $this->merge(['notifications' => $notifications]);
    }
    
    // Trim security roles
    // ...
}
```

**Rules Changed**: `required` → `sometimes|required`, support partial updates

---

## 7. NotificationTestRequest.php (MODIFIED)
**Path**: `app/Http/Requests/Settings/NotificationTestRequest.php`

**Changes Added**:
```php
protected function prepareForValidation(): void
{
    // Trim target and message
    if ($this->has('target')) {
        $this->merge(['target' => trim($this->input('target'))]);
    }
    if ($this->has('message')) {
        $message = trim($this->input('message'));
        $this->merge(['message' => $message === '' ? null : $message]);
    }
}

public function rules(): array
{
    $rules = [
        'channel' => ['required', Rule::in(['email', 'whatsapp'])],
        'message' => ['nullable', 'string', 'max:1000'],
    ];

    // Dynamic validation based on channel
    if ($this->input('channel') === 'email') {
        $rules['target'] = ['required', 'email', 'max:255'];
    } elseif ($this->input('channel') === 'whatsapp') {
        $rules['target'] = [
            'required',
            'string',
            'max:50',
            'regex:/^(\+62|62|0)[0-9]{9,13}$/',
        ];
    } else {
        $rules['target'] = ['required', 'string', 'max:255'];
    }

    return $rules;
}

public function messages(): array
{
    return [
        'target.email' => 'Target harus berupa alamat email yang valid.',
        'target.regex' => 'Nomor WhatsApp harus dalam format Indonesia (+62xxx atau 08xxx).',
    ];
}
```

**Benefit**: Dynamic validation per channel, better error messages

---

## 8. LocalizationRetentionController.php (MODIFIED)
**Path**: `app/Http/Controllers/Api/Settings/LocalizationRetentionController.php`

**Changes**:
```php
public function update(LocalizationSettingsRequest $request): JsonResponse
{
    Gate::authorize('manage-settings');
    $data = $request->validated();

    // Normalize storage_folder_path with better handling
    if (isset($data['retention']['storage_folder_path'])) {
        $folder = trim($data['retention']['storage_folder_path'], '/');
        $data['retention']['storage_folder_path'] = $folder;
        $data['retention']['base_path'] = $folder ? $folder . '/' : '';
    } elseif (isset($data['retention']) && array_key_exists('storage_folder_path', $data['retention'])) {
        // Explicitly set to empty/null - clear base_path too
        $data['retention']['base_path'] = '';
    }

    // Build payload conditionally (only include what was sent)
    $payload = [];
    if (isset($data['localization'])) {
        $payload['locale'] = $data['localization'];
    }
    if (isset($data['retention'])) {
        $payload['retention'] = $data['retention'];
    }

    $this->writer->put($payload, 'UPDATE_LOCALE_RETENTION', $request->user());

    $snapshot = $this->builder->build();

    return response()->json([
        'localization' => Arr::get($snapshot, 'localization', []),
        'retention' => Arr::get($snapshot, 'retention', []),
    ]);
}
```

**Benefit**: Better handling for empty/null storage_folder_path

---

## 9. WhatsAppService.php (NEW)
**Path**: `app/Services/Notifications/WhatsAppService.php`

**Purpose**: Stub implementation untuk WhatsApp notifications (replace with actual provider later)

**Key Methods**:
```php
public function send(string $target, string $message): array
{
    // Validate phone number
    if (!$this->isValidPhoneNumber($target)) {
        return ['status' => 'error', 'message' => 'Invalid phone number format'];
    }
    
    // Log message (stub mode)
    Log::info('WhatsApp message stub', [
        'target' => $target,
        'message' => $message,
        'timestamp' => now()->toIso8601String(),
    ]);
    
    return [
        'status' => 'delivered',
        'message' => 'WhatsApp message sent successfully (stub mode - check logs)',
        'delivered_at' => now()->toIso8601String(),
    ];
}

public function isConfigured(): bool { return false; } // stub mode
public function getStatus(): array { ... }
```

**Benefit**: Testing works without actual WhatsApp API

---

## 10. NotificationTestService.php (MODIFIED)
**Path**: `app/Services/Notifications/NotificationTestService.php`

**Changes**:
- Changed from `App\Services\WhatsAppService` to `App\Services\Notifications\WhatsAppService`
- Better error handling with try-catch
- Enhanced logging for email (fallback to log driver)
- Improved response format with `delivered_at` timestamp

**Key Changes**:
```php
private function sendEmail(string $target, ?string $message): array
{
    try {
        $body = $message ?: 'Tes notifikasi LIMS - Email berfungsi dengan baik.';
        
        Mail::raw($body, function ($mail) use ($target) {
            $mail->to($target)->subject('Tes Notifikasi LIMS');
        });

        // Fallback to log if mail driver is 'log'
        if (config('mail.default') === 'log') {
            Log::info('Test email notification (log driver)', [
                'to' => $target,
                'body' => $body,
            ]);
        }

        return [
            'status' => 'delivered',
            'message' => sprintf('Email berhasil dikirim ke %s', $target),
            'delivered_at' => now()->toIso8601String(),
        ];
    } catch (\Throwable $e) {
        Log::error('Email notification test failed', [...]);
        return [
            'status' => 'failed',
            'message' => 'Gagal mengirim email: ' . $e->getMessage(),
        ];
    }
}
```

---

## 11. Feature Tests (ALL NEW - 5 files)

### **NumberingSettingsTest.php** (11 tests)
- Test current snapshot, update, partial update
- Test preview pattern
- Test auth/authz
- Test validation (required, empty strings → null)

### **LocalizationRetentionSettingsTest.php** (14 tests)
- Test update localization, retention
- Test partial updates
- Test storage path validation (valid paths, reject absolute/traversal)
- Test purge days nullable, minimum validation
- Test auth/authz

### **NotificationsSecuritySettingsTest.php** (15 tests)
- Test update notifications, security
- Test partial updates
- Test email notification (Mail fake)
- Test WhatsApp notification (Log spy)
- Test validation (channel, email format, phone format)
- Test auth/authz

### **TemplatesSettingsTest.php** (15 tests)
- Test list, upload (upsert), activate, delete
- Test preview (stream, 404 if missing)
- Test Storage fake
- Test auth/authz

### **DocumentsTest.php** (13 tests)
- Test list documents (filtered, authz)
- Test delete (own, admin, unauthorized)
- Test audit log
- Test signed URLs
- Test missing file handling

**Total: 68 feature tests**

---

## Summary of Changes

### **Created (9 files)**
1. `app/Repositories/SettingsRepository.php`
2. `app/Services/Notifications/WhatsAppService.php`
3. `tests/Feature/Api/Settings/NumberingSettingsTest.php`
4. `tests/Feature/Api/Settings/LocalizationRetentionSettingsTest.php`
5. `tests/Feature/Api/Settings/NotificationsSecuritySettingsTest.php`
6. `tests/Feature/Api/Settings/TemplatesSettingsTest.php`
7. `tests/Feature/Api/DocumentsTest.php`

### **Modified (7 files)**
1. `app/Services/Settings/SettingsWriter.php`
2. `app/Http/Requests/Settings/NumberingSettingsRequest.php`
3. `app/Http/Requests/Settings/BrandingSettingsRequest.php`
4. `app/Http/Requests/Settings/NotificationsSecurityRequest.php`
5. `app/Http/Requests/Settings/NotificationTestRequest.php`
6. `app/Http/Controllers/Api/Settings/LocalizationRetentionController.php`
7. `app/Services/Notifications/NotificationTestService.php`

### **Unchanged but mentioned**
- `app/Http/Requests/Settings/LocalizationSettingsRequest.php` (already good)
- All other controllers (already properly structured)

---

## Verification

Run these commands to verify:

```bash
# 1. Check routes
php artisan route:list | grep -E '(settings|documents)'

# 2. Run all tests
php artisan test

# 3. Run settings tests only
php artisan test --filter Settings

# 4. Run documents tests only
php artisan test tests/Feature/Api/DocumentsTest.php

# 5. Check specific setting
php artisan tinker --execute="settings('numbering.sample_code.pattern')"
```

Expected output:
- ✅ All routes present
- ✅ 68 feature tests pass
- ✅ Settings accessible via helper

---

## Migration Guide

**No database migrations needed** - uses existing `system_settings` table.

**Deploy steps**:
1. Pull changes
2. Run `composer install` (if needed)
3. Run `php artisan test` to verify
4. Deploy to production
5. Monitor logs for any issues

**Rollback plan**: All changes are backward-compatible. Existing endpoints continue to work.
