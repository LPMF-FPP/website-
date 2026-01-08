# LPMF Feature Implementation: Monitoring, Instruments & Weighing

## Overview

Implementasi 3 kapabilitas baru terintegrasi dengan workflow LPMF dan siap audit (ISO 17025/QMS):

1. **Monitoring lingkungan** (suhu + opsional kelembaban)
2. **Pencatatan instrumen** event-driven per metode pengujian
3. **Log penimbangan UV-VIS** di tahap preparasi sampel
4. **Cetak log bulanan** (PDF) untuk semua kategori

---

## Phase 1: Database Migrations & Models

### 1.1 Environment Monitoring Tables

- [x] Create migration `create_environment_locations_table`
    - id, name, type (room|fridge|freezer|other), target_temp_min/max, target_hum_min/max (nullable)
    - schedule_windows (JSON), is_active, pic_user_id (nullable), timestamps
- [x] Create migration `create_environment_readings_table`
    - id, location_id (FK), measured_at, temperature_c, humidity_rh (nullable)
    - entered_by (FK users), source (manual|import|iot), notes, correction_of_id (self-ref nullable)
    - correction_reason (nullable), created_at (append-only, no updated_at needed)
- [x] Create Model `EnvironmentLocation` with relationships
- [x] Create Model `EnvironmentReading` with relationships and scopes

### 1.2 Instrument Tables

- [x] Create migration `create_instruments_table`
    - id, code (unique), name, category, is_active, timestamps
- [x] Create migration `create_instrument_assets_table`
    - id, instrument_id (FK), asset_code, serial_number, location, status, calibration_due_at, timestamps
- [x] Create migration `create_method_instrument_requirements_table`
    - id, method_code (uv_vis|gc_ms|lc_ms), instrument_id (FK), mandatory, usage_type (PREP|RUN), sequence, timestamps
- [x] Create migration `create_instrument_usage_logs_table`
    - id, test_request_id (FK), sample_id (FK), method_code, instrument_asset_id (FK)
    - usage_type, logged_at, performed_by (FK users), notes, created_at
- [x] Create Model `Instrument`
- [x] Create Model `InstrumentAsset` with status scopes
- [x] Create Model `MethodInstrumentRequirement`
- [x] Create Model `InstrumentUsageLog`

### 1.3 UV-VIS Weighing Columns

- [x] Create migration `add_uvvis_weighing_columns_to_samples_table`
    - uvvis_weighed_grams (decimal 12,4 nullable)
    - uvvis_weighed_by (FK users nullable)
    - uvvis_weighed_at (timestamp nullable)
- [x] Update Sample model with new fillable fields and relationship

### 1.4 Enums

- [x] Create Enum `EnvironmentLocationType` (room, fridge, freezer, other)
- [x] Create Enum `InstrumentUsageType` (PREP, RUN)
- [x] Create Enum `ReadingSource` (manual, import, iot)
- [x] Create Enum `InstrumentAssetStatus` (active, maintenance, out_of_service, calibration_due)

---

## Phase 2: Settings Section "Monitoring dan Pencatatan"

### 2.1 Backend Settings Support

- [x] Add `monitoring_logging` to allowedRoots in `SettingsController::update()`
- [x] Add `monitoring_logging` to allowedRoots in `SettingsController::extractPayload()`
- [x] Create API endpoint `PUT /api/settings` for monitoring_logging (uses existing endpoint)
- [x] Update `GET /api/settings` to include `monitoring_logging` block

### 2.2 Settings UI

- [x] Create partial `settings/partials/monitoring-logging.blade.php`
    - Sub-card 1: Environment Monitoring (work hours, windows, humidity toggle)
    - Sub-card 2: Instrument Logging toggle
    - Sub-card 3: UV-VIS Weighing toggle
- [x] Add sidebar button in `settings/index.blade.php`
- [x] Add section display in settings content area
- [x] Create/update JS for settings Alpine component to handle new section

---

## Phase 3A: Environment Monitoring Feature

### 3.1 Service Layer

- [x] Create `EnvironmentMonitoringService`
    - `getDueListForUser($user, $date)` - returns locations due/overdue based on window
    - `getActiveWindow($location, $datetime)` - returns current window or null if outside
    - `canInputForWindow($location, $window, $datetime)` - window pagi tertutup jika lewat, siang masih bisa
    - `validateMetricsByLocation($location, $payload, $settings)` - enforce temp required, humidity per config
    - `detectOutOfRange($reading, $location)` - returns status object
    - `createReading($location, $data, $user)` - insert new reading
    - `createCorrection($originalReading, $correctedData, $reason, $user)` - insert correction

### 3.2 Controller & Routes

- [x] Create `EnvironmentMonitoringController`
    - `index()` - list locations with status
    - `storeReading(Request)` - submit reading
    - `showCorrectionForm($reading)` - form for correction
    - `storeCorrection(Request, $reading)` - submit correction
- [x] Create API controller for dashboard banner
    - `GET /api/monitoring/environment/due` - returns due locations for current user
- [x] Register routes in web.php and api.php

### 3.3 Views

- [x] Create `monitoring/environment/index.blade.php`
    - Location cards with status (due/filled/overdue/out-of-range)
    - Quick input modal/form
    - Correction modal
- [x] Create `monitoring/environment/manage.blade.php` - Admin CRUD for locations
- [x] Create `monitoring/environment/correction.blade.php` - Correction form

### 3.4 Dashboard Integration

- [x] Update `DashboardController::index()` to fetch due environment tasks
- [x] Add banner section in `dashboard.blade.php` for environment notifications
- [x] Banner shows due/overdue locations with "Input Monitoring Sekarang" button

---

## Phase 3B: Instrument Logging Feature

### 3.1 Service Layer

- [x] Create `InstrumentLoggingService`
    - `requirementsForMethod($method_code)` - get required instruments from settings/DB
    - `getAvailableAssets($instrument_id)` - assets with valid status
    - `validateSelections($method_code, $selections)` - check all mandatory filled
    - `createUsageLogs($sample, $method_code, $assetSelections, $user)` - batch insert logs
    - `hasCompletedRequirements($sample, $stage)` - check if all mandatory logged

### 3.2 Controller & Routes

- [x] Create `InstrumentLoggingController` or extend `SampleTestProcessController`
    - `GET /api/samples/{sample}/instrument-requirements` - return requirements + available assets
    - `POST /api/samples/{sample}/instrument-usage` - submit selections
    - `GET /api/samples/{sample}/uvvis-weighing` - check weighing status
    - `POST /api/samples/{sample}/uvvis-weighing` - submit weighing data
- [x] Add gate in `WorkflowService::completeTestProcess()` for INSTRUMENTATION stage

### 3.3 Views & UI Integration

- [x] Update `sample-processes/edit.blade.php` for INSTRUMENTATION stage
    - Add "Instrumen yang digunakan" block
    - Auto-populate required instruments based on sample's test_methods
    - Dropdown for asset selection per requirement
    - Validation before finalize
- [x] Create/update JS for instrument selection UI (Alpine.js components)

---

## Phase 3C: UV-VIS Weighing Feature

### 3.1 Service/Validation

- [x] Add method in `InstrumentLoggingService` to check weighing requirement
    - `requiresUvvisWeighing($sample)` - check if test_methods contains uv_vis AND setting enabled
    - `hasCompletedUvvisWeighing($sample)` - check if uvvis_weighed_grams is filled
    - `recordUvvisWeighing($sample, $grams, $user)` - record weighing data

### 3.2 Gate Integration

- [x] Add gate in `WorkflowService::validatePreparationGate()`
    - Block finalize if uv_vis method AND weighing enabled AND fields null
- [x] Auto-set `uvvis_weighed_by` and `uvvis_weighed_at` on save (via API endpoint)

### 3.3 Views

- [x] Update preparation form/view to show weighing field conditionally
    - Input for gram (decimal)
    - Read-only display of technician (current user)
    - Timestamp after save
- [x] Conditional display based on test_methods and settings toggle (Alpine.js component)

---

## Phase 4: Monthly PDF Reports

### 4.1 Report Controller

- [x] Create `Reports/MonthlyLogReportController`
    - `index()` - Report generation UI
    - `environmentReport(Request)` - GET /reports/monthly-logs/environment?location_id=&month=YYYY-MM
    - `instrumentReport(Request)` - GET /reports/monthly-logs/instrument?asset_id=&month=YYYY-MM
    - `weighingReport(Request)` - GET /reports/monthly-logs/weighing?month=YYYY-MM

### 4.2 PDF Templates

- [x] Create `pdf/environment-monthly.blade.php`
    - Header with location name, month, generated date
    - Table: Date, Window, Temp, Humidity, Status, Entered By, Notes
    - Handle empty data: "Tidak ada data untuk periode ini"
- [x] Create `pdf/instrument-monthly.blade.php`
    - Header with asset info, month
    - Table: Date, Sample Code, Request Number, Usage Type, Performed By, Notes
- [x] Create `pdf/weighing-monthly.blade.php`
    - Header with month, generated date
    - Table: Date, Receipt Number, Sample Code, Grams, Technician

### 4.3 Document Integration

- [x] Use `DocumentService::storeGenerated()` to save reports (optional - manual download currently)
- [x] Add document_types: environment_monthly_log, instrument_monthly_log, uvvis_weighing_monthly_log
- [x] Add metadata: month, location_id/asset_id, generated_by

### 4.4 Report Access UI

- [x] Create `reports/monthly-logs.blade.php` - Report generation UI with forms for each report type
    - Environment: location selector, month picker
    - Instrument: asset selector, month picker
    - Weighing: month picker only
    - Weighing: in settings or dedicated report page

---

## Phase 5: Testing & Verification

### 5.1 Migrations

- [x] Run all migrations successfully
- [x] Verify table structures in database

### 5.2 Settings

- [x] Verify settings save and load correctly
- [x] Test toggle persistence

### 5.3 Environment Monitoring

- [x] Test due list calculation
- [x] Test window closure logic (pagi tertutup jika lewat)
- [x] Test reading input and correction
- [x] Test dashboard banner appears/disappears

### 5.4 Instrument Logging

- [x] Test requirement loading per method
- [x] Test asset selection and validation
- [x] Test gate blocks finalize without complete selection

### 5.5 UV-VIS Weighing

- [x] Test conditional display based on method and setting
- [x] Test gate blocks finalize without weighing
- [x] Test auto-fill of user and timestamp

### 5.6 Reports

- [x] Test PDF generation for each report type
- [x] Test empty data handling
- [x] Test document storage

---

## Acceptance Criteria (from spec)

- [x] `/settings` has "Monitoring dan Pencatatan" section; toggles persist on reload
- [x] Dashboard shows due location banner (07-09, 13-15 windows); disappears after input
- [x] Environment input: humidity field shows only if enabled; validation works
- [x] Instrument process: GC method shows centrifuge+sonicator+GC mandatory; can't finalize without valid asset selection
- [x] Preparation UV-VIS: gram field required for uv_vis (if toggle enabled); weighed_by/at auto-fill
- [x] Monthly PDF reports generate for any month; empty data shows "Tidak ada data"; saved as Document

---

## Notes

- Window logic: Pagi (07:00-09:00) tertutup jika sudah lewat jam 09:00, siang (13:00-15:00) tetap bisa
- Correction: Insert new record with `correction_of_id` pointing to original
- Reports: Manual trigger only, no auto-generation
- Instruments & Locations: Admin will input master data manually via UI
