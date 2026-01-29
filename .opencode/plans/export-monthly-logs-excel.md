# Plan: Export Monthly Logs to Excel/CSV

> **Status:** Proposal (Read-Only)
> **Goal:** Enable downloading monthly logs (Environment & Instrument) in Excel-compatible formats (CSV/XLSX) and ensure reports are generated per item (e.g., separate Chiller Jan, Freezer Jan).

## 1. Analysis

The user has two requests:

1.  **Excel Output:** Currently, the system generates PDF reports (`$pdfService->htmlToPdf`). We need to add an option to export raw data as CSV or Excel.
2.  **Per-Item Reports:** The current controller supports filtering by `location_id` (Environment) and `asset_id` (Instrument). The user wants "per item" reports (e.g., Chiller January). This functionality already exists logic-wise but needs to be exposed as a download option (likely "Download All as ZIP" or individual buttons).

**Technical Approach:**

- **Format:** We will use **CSV** (Comma Separated Values) as it's native to PHP, memory-efficient, and opens perfectly in Excel. Avoiding heavy dependencies like `phpoffice/phpspreadsheet` unless already installed keeps the system lightweight.
- **Per-Item Logic:** The controller already accepts `location_id`. We will modify the frontend (View) to allow selecting a specific location/asset or "All". If "All" is selected, we could either generate one big sheet or a ZIP of individual files. Based on the user's "per item" request, a loop generating individual files (zipped) or just strictly enforcing "Select Location" before download is safer.

## 2. Implementation Plan

### Phase 1: Controller Modifications

Modify `App\Http\Controllers\Reports\MonthlyLogReportController.php`.

**New Method: `exportEnvironmentCsv`**

- Accepts `location_id` and `month`.
- Fetches data using `EnvironmentMonitoringService`.
- Generates CSV string using `fputcsv`.
- Returns `StreamedResponse` for download.

**New Method: `exportInstrumentCsv`**

- Accepts `asset_id` and `month`.
- Fetches data using `InstrumentLoggingService`.
- Generates CSV.
- Returns response.

### Phase 2: Routing

Add routes for the new CSV export methods in `routes/web.php`.

### Phase 3: View Updates

Update `resources/views/reports/monthly-logs.blade.php` (assumed view path) to add "Export Excel/CSV" buttons next to the PDF buttons.

## 3. Detailed Logic (CSV Generation)

**Environment CSV Columns:**
`Date`, `Time`, `Location`, `Temperature (°C)`, `Humidity (%RH)`, `Recorded By`, `Source`, `Notes`.

**Instrument CSV Columns:**
`Date`, `Start Time`, `End Time`, `Asset Name`, `Asset Code`, `User`, `Project/Sample`, `Condition`, `Notes`.

## 4. Addressing "Per Item" Request

The current `environmentReport` method logic:

```php
if ($locationId) {
    // Get readings for specific location
} else {
    // Get readings for ALL locations
}
```

To satisfy "per item", the user simply needs to select the specific location in the dropdown (which presumably exists in the UI). We will ensure the CSV export respects this `location_id` parameter.

If the user wants to download _all_ items as _separate_ files in one go, we would need a "Download All (ZIP)" feature. For now, we'll assume standard filtering is sufficient, but I'll add a note about ZIP if requested.

---

**Does this plan align with your needs?**

1.  **CSV Download** (opens in Excel) instead of just PDF.
2.  **Per-item** filtering is already supported via the `location_id` parameter; we just need to ensure the export button uses it.
