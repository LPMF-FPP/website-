# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Environment monitoring system for temperature and humidity tracking
    - `EnvironmentMonitoringService` with window logic (morning 07:00-09:00, afternoon 12:00-14:00)
    - `EnvironmentMonitoringController` with CRUD for locations and reading input
    - Location management UI at `/monitoring/environment/manage`
    - Correction workflow with audit trail (append-only records)
    - Dashboard notification banner for due/overdue locations
- Instrument logging feature with event-driven recording per test method
    - `InstrumentLoggingService` with requirements validation per method
    - `InstrumentLoggingController` with API endpoints for workflow integration
    - Gate validation in `WorkflowService` blocks INSTRUMENTATION completion without mandatory instruments
    - Alpine.js UI component for instrument selection in sample process edit form
- UV-VIS weighing log at sample preparation stage
    - Weighing validation methods in `InstrumentLoggingService`
    - Gate validation in `WorkflowService` blocks PREPARATION completion for UV-VIS samples without weighing
    - Alpine.js UI component for weighing input in sample process edit form
- Monthly PDF reports for environment, instruments, and weighing logs
    - `MonthlyLogReportController` with PDF generation via DomPDF
    - PDF templates: `environment-monthly.blade.php`, `instrument-monthly.blade.php`, `weighing-monthly.blade.php`
    - Report generation UI at `/reports/monthly-logs`
- New settings section "Monitoring dan Pencatatan" for admin configuration
- API routes for instrument logging:
    - `GET /api/samples/{sample}/instrument-requirements`
    - `POST /api/samples/{sample}/instrument-usage`
    - `GET /api/samples/{sample}/uvvis-weighing`
    - `POST /api/samples/{sample}/uvvis-weighing`

### Changed

- Extended workflow gates to enforce mandatory field completion per stage

### Fixed

- (none yet)

---

## [Previous Versions]

_(No previous changelog entries - this file was created to track the new monitoring and logging features implementation)_
