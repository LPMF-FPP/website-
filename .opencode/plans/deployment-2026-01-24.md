# Deployment Plan: Numbering Repair & Label Updates

> **Status:** Pending Execution
> **Goal:** Deploy "Numbering Gap Prevention/Repair" and "Label Sisa Styling" updates to production.

## 1. Pre-Deployment Verification (Local)

Ensure integrity before pushing.

- [ ] **Run PHP Tests**: Verify new logic and ensure no regressions.

    ```bash
    php artisan test tests/Feature/NumberingRollbackOnDeleteTest.php
    php artisan test tests/Feature/NumberingRepairReclaimTest.php
    # Optional: Run full suite if time permits
    # php artisan test
    ```

- [ ] **Run Critical Audits**: Ensure compliance.
    ```bash
    npm run audit:critical
    ```

## 2. Version Control

Commit all changes to the repository.

- [ ] **Stage Files**:

    ```bash
    git add app/Http/Controllers/Api/Settings/NumberingRepairController.php
    git add app/Models/NumberingChangeLog.php
    git add app/Models/Sample.php
    git add app/Models/TestRequest.php
    git add app/Services/NumberingRepairService.php
    git add resources/views/labels/remaining-sheet.blade.php
    git add resources/views/settings/partials/numbering-repair.blade.php
    git add routes/api.php
    git add tests/Feature/NumberingRepairReclaimTest.php
    git add tests/Feature/NumberingRollbackOnDeleteTest.php
    ```

- [ ] **Commit**:

    ```bash
    git commit -m "feat: numbering repair system and label styling updates

    - Implemented 'Rollback on Delete' for TestRequest and Sample (using 'deleted' event for safety)
    - Added 'Reclaim Gap' feature to Numbering Repair settings (UI + API + Service)
    - Removed deprecated legacy numbering methods
    - Updated 'Label Sisa' sheet styling (logos, spacing, layout)"
    ```

- [ ] **Push**:
    ```bash
    git push origin main
    ```

## 3. Production Deployment

Execute deployment on the production server.

- [ ] **Connect & Update**:

    ```bash
    sshpass -p 'LPMFjaya1' ssh lpmf-admin@192.168.0.206 "cd /var/www/lis && git pull"
    ```

- [ ] **Post-Deployment Optimization**:
    ```bash
    # Clear old caches and build new optimized cache (Config, Routes, Events, Views)
    sshpass -p 'LPMFjaya1' ssh lpmf-admin@192.168.0.206 "cd /var/www/lis && php artisan optimize && php artisan view:cache"
    ```

## 4. Post-Deployment Verification

Verify features on the live site.

- [ ] **Check Label**: Print "Label Sisa" sheet from a request to verify logos and layout.
- [ ] **Check Settings**: Go to `Settings > Numbering Repair` and verify the UI loads correctly.
- [ ] **Optional Test**: Create a dummy sample (last in sequence) and delete it to verify counter rollback (if safe to do so).

---

**Ready to execute?**
