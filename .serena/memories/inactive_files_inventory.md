# Inactive Files Inventory

Ditemukan file-file yang sudah tidak aktif tetapi masih ada di repository.

## DETAILED ANALYSIS

### 🔴 PHP DEBUG/TEST FILES - DETAILED ANALYSIS

#### **test.php** - DATABASE CONNECTION TEST
- **Content**: Simple PDO connection test for PostgreSQL
- **Status**: ✅ **SAFE TO DELETE** - Generic database test, not used in application
- **References**: None found in codebase
- **Created for**: Initial database setup verification
- **Recommendation**: DELETE - Replace with proper Laravel migration tests if needed

#### **test-preview-debug.php** - BLADE TEMPLATE PREVIEW DEBUG
- **Content**: Direct controller testing for BladeTemplateEditorController::preview()
- **Status**: ⚠️ **CONDITIONAL KEEP** 
- **References**: Referenced in markdown docs (BLADE_PREVIEW_ERROR_HANDLING_COMPLETE.md)
- **Purpose**: Debug preview endpoint issues
- **Recommendation**: Keep in `/docs` or `/tests` folder if still debugging, otherwise DELETE

#### **test-null-removal.php** - SETTINGS NULL REMOVAL TEST
- **Content**: Test script for removeNullLeaves() function (settings cleanup)
- **Status**: ✅ **SAFE TO DELETE** - Per SETTINGS_QA_VALIDATION_SUMMARY.md (already noted as "can remove")
- **References**: Mentioned in QA summary but not used in actual code
- **Recommendation**: DELETE - Logic should be unit tested in `/tests` if needed

#### **REFACTORED_METHODS.php** - OLD CODE REFERENCE
- **Content**: Legacy code refactoring comments (186 lines), reference methods for RequestController
- **Status**: ✅ **SAFE TO DELETE** - Old refactoring notes, not used in application
- **References**: None found in active codebase (only in memory)
- **Purpose**: Historical reference for past code changes
- **Recommendation**: DELETE - Archive to `/docs/archived` if historical reference needed

### 🔴 ROOT LEVEL MISCELLANEOUS FILES
1. **er->role = 'admin';** - CORRUPTED FILE (invalid filename, probably from failed paste operation)
2. **sync-public-assets.bat** - Windows-only batch file for syncing assets
3. **REFACTORED_METHODS.php** - Old refactoring notes (tidak seharusnya di root)

## 📁 SCRIPT SH DIRECTORY - DETAILED ANALYSIS (Testing Scripts)

All 5 scripts in `script sh/` are **DEVELOPMENT VERIFICATION SCRIPTS**.

#### **test-settings-preview.sh** - SETTINGS PREVIEW VERIFICATION
- **Content**: Tests preview functionality for settings (server check, Vite build, endpoints)
- **Status**: ⚠️ **CONDITIONAL KEEP** - Useful for dev verification
- **References**: Part of settings development workflow
- **Recommendation**: Move to `/docs/verification-scripts/` if keeping, else DELETE

#### **test-preview-payload-fix.sh** - PAYLOAD FIX VERIFICATION
- **Status**: ⚠️ **CONDITIONAL KEEP** - Related to past payload fix
- **Recommendation**: Delete if fix is already verified in tests, else keep as reference

#### **test-container-fix.sh** - CONTAINER FIX VERIFICATION
- **Status**: ⚠️ **CONDITIONAL KEEP** - Related to container-related fixes
- **Recommendation**: Delete if verified in unit tests

#### **test-preview-reactivity.sh** - REACTIVITY VERIFICATION
- **Status**: ⚠️ **CONDITIONAL KEEP** - Alpine.js reactivity testing
- **Recommendation**: Delete if verified in tests

#### **test-template-audit.sh** - TEMPLATE AUDIT VERIFICATION
- **Status**: ⚠️ **CONDITIONAL KEEP** - Template audit verification
- **Recommendation**: Delete if audit system is mature

### Shell Scripts Summary
- **Total**: 5 scripts
- **Usage**: Development verification only
- **Status**: **NOT integrated into CI/CD** (seen in markdown but not in actual workflow)
- **Recommendation**: **CLEANUP** - Move to docs or delete after verifying tests cover them

## 🌐 PUBLIC DIRECTORY - DETAILED ANALYSIS (HTML Test Files)

#### **public/test-design-system.html**
- **Status**: ⚠️ **DUPLICATE** - Same content as root `design-system-demo.html`
- **References**: Mentioned in markdown (PRODUCTION-DEPLOYMENT.md)
- **Recommendation**: DELETE - Keep only root version; public version is unnecessary copy

#### **public/test-documents-api.html**
- **Content**: API testing page for documents endpoint
- **Status**: ⚠️ **DEV-ONLY** - Development/debugging tool
- **Usage**: Not referenced in application code
- **Recommendation**: Move to `/docs/testing-tools/` or DELETE if no longer needed

## 📝 MARKDOWN DOCUMENTATION - DETAILED ANALYSIS

### Root Level Markdown Files (Temporary Development Notes)

Total found: **6 files** in root, multiple related files in `/markdown` directory

#### In ROOT directory:
1. **ALPINE_PREVIEW_ERROR_NULL_SAFETY_FIX.md** - Alpine.js null safety fix notes
2. **BLADE_EDITOR_IMPLEMENTATION_SUMMARY.md** - Blade editor implementation
3. **BLADE_EDITOR_PREVIEW_FEATURE.md** - Feature documentation
4. **BLADE_PREVIEW_ERROR_HANDLING_COMPLETE.md** - Error handling complete notes
5. **BLADE_TEMPLATE_EDITOR.md** - Blade editor documentation
6. **BLADE_TEMPLATE_PREVIEW_COMPLETE_FIX.md** - Preview fix completion
7. **CHANGELOG_BLADE_EDITOR.md** - Blade editor changelog
8. **PREVIEW_ENDPOINT_FIX_VERIFICATION.md** - Preview fix verification

**Status**: ✅ **CONSOLIDATE & ARCHIVE**
- These are temporary development notes from fixing Blade template editor
- Should be consolidated into `/patcher/` documentation or archived
- Not end-user facing documentation

### In `/markdown` directory:
**Total**: ~113 markdown files (excessive!)

**Analysis**:
- Many are temporary fix documentation (FIX_*, SETTINGS_*, GRAPESJS_*, TEMPLATE_*, etc.)
- Should be consolidated into main documentation
- Create `/markdown/archived/` subdirectory
- Keep only essential reference docs in root

**Key Issues**:
- Duplicated documentation (e.g., _SUMMARY.md vs _REPORT.md vs _COMPLETE.md)
- Old fixes documented separately instead of in main docs
- Makes it hard to find current/authoritative docs

## 🛠️ RECOMMENDATIONS

### IMMEDIATE REMOVAL (HIGH CONFIDENCE)
- [ ] **er->role = 'admin';** - Corrupted file, definitely remove
- [ ] **test.php** - No clear purpose
- [ ] **test-preview-debug.php** - Debug script only
- [ ] **test-null-removal.php** - As per SETTINGS_QA_VALIDATION_SUMMARY.md
- [ ] **REFACTORED_METHODS.php** - Should be in docs if needed at all
- [ ] **mcp-server.prev.log** - MCP server log, not needed in repo
- [ ] **mcp-server.log** - MCP server log, not needed in repo

### REVIEW & CLEANUP (MEDIUM CONFIDENCE)
- [ ] **test-blade-editor.sh** - Check if needed, else move to docs/CI
- [ ] **test-preview-fix.sh** - Check if still used for any verification
- [ ] **script sh/*.sh** - All test scripts, verify they're not needed before cleanup
- [ ] **test-alpine-preview-error.html** - Standalone test, consider if still needed
- [ ] **public/test-design-system.html** - Duplicate of root design-system-demo.html?
- [ ] **public/test-documents-api.html** - Used for API testing in development

### VERIFY BEFORE REMOVING (LOW CONFIDENCE)
- [ ] **test-safe-overlay.html** - Used for Safe Mode v2 verification (keep if still validating CSS)
- [ ] **design-system-demo.html** - Used for design system reference
- [ ] **theme-demo.html** - Used for theme demonstration
- [ ] **sync-public-assets.bat** - Windows helper, check if Windows users still need it

### DOCUMENTATION FILES (CONSIDER ARCHIVING)
Many markdown files are temporary development notes. Consider:
- Moving to `docs/archived/` or `markdown/archived/`
- Or consolidate into `report/README.md` and `patcher/` docs
