# ✅ FIX: Laporan Hasil Uji - Data Mapping Correction

**Tanggal:** 7 Oktober 2025  
**Status:** ✅ **FIXED & VERIFIED**  
**Issue:** Data mapping tidak sesuai dengan requirement

---

## 🔍 Problem Identified

### Before (Incorrect Mapping)

| Field | Template Variable | Source | Value |
|-------|-------------------|--------|-------|
| Nama Pelanggan | `customer_unit` ❌ | jurisdiction | "Polres magelang" |
| Alamat Pelanggan | `customer_address` ❌ | delivery_address | "-" |
| Tanggal Penerimaan | `received_at` ❓ | received_at | Physical receipt date |

**Problems:**
1. ❌ Nama Pelanggan menampilkan satuan (Polres), bukan nama penyidik
2. ❌ Alamat Pelanggan kosong ("-")
3. ❓ Tanggal penerimaan mungkin salah field

---

## ✅ Solution Implemented

### Corrected Mapping

| Field | Template Variable | Source | Value | Description |
|-------|-------------------|--------|-------|-------------|
| **Nama Pelanggan** | `customer_name` ✅ | `rank + name` | "AIPDA Syaba" | Nama penyidik |
| **Alamat Pelanggan** | `customer_unit` ✅ | `jurisdiction` | "Polres magelang" | Asal satuan |
| **Tanggal Penerimaan** | `received_date` ✅ | `submitted_at` → `received_at` | "05 October 2025" | Tanggal formulir diisi |

---

## 📊 Data Flow

```
┌─────────────────────────────────────────────────┐
│ Database: investigators                         │
│ ├─ jurisdiction: "Polres magelang"             │
│ ├─ rank: "AIPDA"                               │
│ └─ name: "Syaba"                               │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ Database: test_requests                         │
│ ├─ submitted_at: "2025-10-05"                  │
│ └─ received_at: "2025-10-03" (fallback)       │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ API Endpoint: /api/sample-processes/6          │
│ ├─ customer_name: "AIPDA Syaba"                │
│ ├─ customer_unit: "Polres magelang"            │
│ └─ received_date: "05 October 2025"            │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ Jinja2 Template: laporan_hasil_uji.html.j2    │
│ ├─ Nama Pelanggan: {{ customer_name }}         │
│ ├─ Alamat Pelanggan: {{ customer_unit }}       │
│ └─ Tanggal Penerimaan: {{ received_date }}     │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ Generated HTML Output                           │
│ ├─ Nama Pelanggan: AIPDA Syaba ✅              │
│ ├─ Alamat Pelanggan: Polres magelang ✅        │
│ └─ Tanggal Penerimaan: 05 October 2025 ✅      │
└─────────────────────────────────────────────────┘
```

---

## 📝 Files Modified

### 1. `templates/laporan_hasil_uji.html.j2` ✅

**Changed:**
```jinja2
BEFORE:
<tr><th>Nama Pelanggan</th><td>{{ customer_unit }}</td></tr>
<tr><th>Alamat Pelanggan</th><td>{{ customer_address }}</td></tr>

AFTER:
<tr><th>Nama Pelanggan</th><td>{{ customer_name }}</td></tr>
<tr><th>Alamat Pelanggan</th><td>{{ customer_unit }}</td></tr>
```

**Impact:** Template now uses correct variables for display

---

### 2. `routes/api.php` ✅

**Changed:**
```php
BEFORE:
'received_date' => $testRequest?->received_at 
    ? $testRequest->received_at->format('d F Y') 
    : '-',

AFTER:
// Tanggal penerimaan = tanggal formulir pengujian diisi (submitted_at)
'received_date' => $testRequest?->submitted_at 
    ? $testRequest->submitted_at->format('d F Y') 
    : ($testRequest?->received_at ? $testRequest->received_at->format('d F Y') : '-'),
```

**Logic:**
1. Prioritize `submitted_at` (tanggal formulir diisi)
2. Fallback to `received_at` if `submitted_at` null
3. Default to "-" if both null

**Impact:** API now returns correct date field

---

## 🧪 Testing & Verification

### Test 1: API Response ✅

```bash
curl http://127.0.0.1:8000/api/sample-processes/6

# Output:
{
  "customer_name": "AIPDA Syaba",         ← ✅ Nama penyidik
  "customer_unit": "Polres magelang",     ← ✅ Asal satuan
  "received_date": "05 October 2025",     ← ✅ Tanggal formulir diisi
  ...
}
```

### Test 2: Generated HTML ✅

```bash
python scripts/generate_laporan_hasil_uji.py --id 6

# Check output file:
# output/laporan-hasil-uji/Laporan_Hasil_Uji_FLHU001.html

# Content shows:
┌────────────────────────────┬─────────────────────┐
│ Nama Pelanggan             │ AIPDA Syaba         │ ✅
│ Alamat Pelanggan           │ Polres magelang     │ ✅
│ Tanggal Penerimaan Sampel  │ 05 October 2025     │ ✅
└────────────────────────────┴─────────────────────┘
```

### Test 3: Web Display ✅

```
URL: http://127.0.0.1:8000/laporan-hasil-uji/Laporan_Hasil_Uji_FLHU001.html

Display shows:
✅ Nama Pelanggan: AIPDA Syaba
✅ Alamat Pelanggan: Polres magelang
✅ Tanggal Penerimaan: 05 October 2025
```

---

## 📋 Data Dictionary

### API Response Fields

| Field | Type | Source | Description | Example |
|-------|------|--------|-------------|---------|
| `customer_name` | string | `investigator.rank + ' ' + investigator.name` | Nama lengkap penyidik dengan pangkat | "AIPDA Syaba" |
| `customer_unit` | string | `investigator.jurisdiction` | Satuan/jurisdiksi penyidik | "Polres magelang" |
| `customer_address` | string | `testRequest.delivery_address` | Alamat pengiriman (tidak digunakan di LHU) | "-" |
| `received_date` | string | `testRequest.submitted_at` (fallback: `received_at`) | Tanggal formulir pengujian diisi | "05 October 2025" |

### Template Variables

| Template Variable | Display Label | Source Field |
|-------------------|---------------|--------------|
| `{{ customer_name }}` | Nama Pelanggan | `customer_name` |
| `{{ customer_unit }}` | Alamat Pelanggan | `customer_unit` |
| `{{ received_date }}` | Tanggal Penerimaan Sampel | `received_date` |

---

## 🎯 Business Logic

### Date Field Logic

**Tanggal Penerimaan Sampel** mengikuti prioritas:

1. **Primary:** `test_requests.submitted_at`
   - Tanggal ketika penyidik mengisi dan submit formulir pengujian
   - Lebih akurat untuk "tanggal penerimaan permintaan"

2. **Fallback:** `test_requests.received_at`
   - Tanggal ketika laboratorium menerima sampel fisik
   - Digunakan jika `submitted_at` kosong

3. **Default:** "-"
   - Jika kedua field kosong

### Why submitted_at over received_at?

| Field | Meaning | Use Case |
|-------|---------|----------|
| `submitted_at` | Tanggal formulir pengujian diisi | ✅ Tanggal penerimaan **permintaan** |
| `received_at` | Tanggal sampel fisik diterima | Tanggal penerimaan **sampel** (bisa berbeda) |

**User requirement:** "Tanggal ketika formulir pengujian diisi" → `submitted_at` ✅

---

## 🔄 Before vs After

### Before Fix

```
Informasi Pelanggan & Sampel
┌────────────────────────────┬─────────────────────┐
│ Nama Pelanggan             │ Polres magelang     │ ❌ Wrong!
│ Alamat Pelanggan           │ -                   │ ❌ Empty!
│ Tanggal Penerimaan Sampel  │ 03 Juli 2025        │ ❓ Maybe wrong date
└────────────────────────────┴─────────────────────┘
```

### After Fix

```
Informasi Pelanggan & Sampel
┌────────────────────────────┬─────────────────────┐
│ Nama Pelanggan             │ AIPDA Syaba         │ ✅ Correct!
│ Alamat Pelanggan           │ Polres magelang     │ ✅ Correct!
│ Tanggal Penerimaan Sampel  │ 05 October 2025     │ ✅ Correct!
└────────────────────────────┴─────────────────────┘
```

---

## 🐛 Troubleshooting

### Issue: Nama Pelanggan still showing wrong data

**Check 1: Regenerate file**
```bash
python scripts/generate_laporan_hasil_uji.py --id 6
```

**Check 2: Verify API**
```bash
curl http://127.0.0.1:8000/api/sample-processes/6 | jq '.customer_name, .customer_unit'
```

**Check 3: Clear browser cache**
```
Ctrl + F5 to hard refresh
```

### Issue: Tanggal Penerimaan wrong

**Check database:**
```sql
SELECT 
  request_number,
  submitted_at,
  received_at
FROM test_requests
WHERE id = (
  SELECT test_request_id 
  FROM samples 
  WHERE id = (
    SELECT sample_id 
    FROM sample_test_processes 
    WHERE id = 6
  )
);
```

**Expected:**
- If `submitted_at` exists → use that
- Else use `received_at`

---

## ✅ Verification Checklist

- [x] ✅ Template uses `customer_name` for Nama Pelanggan
- [x] ✅ Template uses `customer_unit` for Alamat Pelanggan
- [x] ✅ API prioritizes `submitted_at` for `received_date`
- [x] ✅ API has fallback to `received_at`
- [x] ✅ API response verified via curl
- [x] ✅ HTML generated successfully
- [x] ✅ Web display shows correct data
- [x] ✅ All three fields display correctly:
  - [x] Nama Pelanggan: AIPDA Syaba
  - [x] Alamat Pelanggan: Polres magelang
  - [x] Tanggal Penerimaan: 05 October 2025

**Status:** ✅ **ALL CHECKS PASSED**

---

## 📊 Summary

| Aspect | Before | After |
|--------|--------|-------|
| **Nama Pelanggan** | Polres magelang ❌ | AIPDA Syaba ✅ |
| **Alamat Pelanggan** | - (empty) ❌ | Polres magelang ✅ |
| **Tanggal Penerimaan** | received_at (maybe wrong) ❓ | submitted_at (correct) ✅ |
| **Data Source** | Mixed/Confused ❌ | Clear & Documented ✅ |
| **Template Mapping** | Incorrect ❌ | Correct ✅ |

---

## 📞 Support

If data still looks wrong:

1. **Verify database values:**
   ```bash
   php artisan tinker
   >>> $p = \App\Models\SampleTestProcess::with('sample.testRequest.investigator')->find(6);
   >>> $i = $p->sample->testRequest->investigator;
   >>> echo $i->rank . ' ' . $i->name;
   >>> echo $i->jurisdiction;
   >>> echo $p->sample->testRequest->submitted_at;
   ```

2. **Check API response:**
   ```bash
   curl http://127.0.0.1:8000/api/sample-processes/6 | jq
   ```

3. **Regenerate with fresh data:**
   ```bash
   python scripts/generate_laporan_hasil_uji.py --id 6
   ```

4. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

**Implementation Date:** 7 Oktober 2025  
**Status:** ✅ **FIXED & PRODUCTION READY**  
**Impact:** Laporan Hasil Uji now displays correct data as per requirements

🎉 **Data mapping is now 100% correct!**
