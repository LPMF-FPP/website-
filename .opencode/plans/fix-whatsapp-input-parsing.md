# Plan: Fix WhatsApp Temperature Input Parsing

> **Status:** Ready for Execution
> **Issue:** Users cannot input temperature data via WhatsApp because of strict case-sensitive matching and rigid parameter parsing (space-separated).
> **Solution:** Implement intelligent command parsing and fuzzy location matching.

---

### 1. Analysis

- **Current Logic:** Expects `params[0]` = Location, `params[1]` = Value. Case-sensitive SQL `LIKE`.
- **Problem:**
    - Multi-word locations (e.g., "ruang gc ms") split into multiple params.
    - "Ruang-GC-MS" vs "ruang-gc-ms" fails (Case sensitivity).
    - "ruang" matches too many or none depending on strictness.

### 2. Implementation Strategy

**File:** `app/Services/WhatsApp/Commands/TemperatureInputCommand.php`

**New Logic:**

1.  **Extract Value:** Find the first numeric parameter. Assign to `$value`.
2.  **Extract Period:** Check for keywords ('pagi', 'siang', 'sore'). Assign to `$period`.
3.  **Extract Location:** Join all remaining parts as `$inputName`.
4.  **Fuzzy Match:**
    - Fetch all active locations from DB.
    - Normalize string: `strtolower`, replace `-/_` with space.
    - Find locations where `normalized_db_name` contains `normalized_input_name`.
5.  **Handle Results:**
    - 0 matches: Error "Lokasi tidak ditemukan".
    - 1 match: Success -> Save.
    - > 1 matches: Error "Terlalu umum, spesifikkan: [List]".

### 3. Execution Steps

1.  **Modify Command Class:** Rewrite `execute` method in `TemperatureInputCommand.php`.
2.  **Deploy:** Copy file to production.
3.  **Test:** Use WhatsApp bot to verify `/suhu ruang gc 15.4`.

---

**Ready to execute?** Type "Execute" to apply the fix.
