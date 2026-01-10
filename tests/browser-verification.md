# 🧪 Browser Verification Test Suite

**Date**: 2026-01-10  
**Version**: v1.2.3  
**Purpose**: Manual browser testing for Party Mode implementations

---

## ✅ AUTOMATED VERIFICATION (COMPLETED)

### Installation & Build

- [x] Alpine.js plugins installed (@alpinejs/collapse v3.15.3, @alpinejs/focus v3.15.3)
- [x] Build successful (no errors)
- [x] Critical audits passed:
    - [x] Guard audit: 0 violations (all overlay files safe)
    - [x] Cascade audit: 1 critical, 108 major (pre-existing, no regressions)
    - [x] Contrast audit: 0 failures

### Code Verification

- [x] Toast store registered in app.js (line 72)
- [x] Alpine.js plugins imported and registered (lines 4-5, 12-13)
- [x] Toast container component exists with ARIA
- [x] Modal portal exists in layout (app.blade.php:81)
- [x] Modal uses x-teleport (modal.blade.php:26)
- [x] Modal uses x-trap.noscroll.inert (modal.blade.php:29)
- [x] Accordion uses x-collapse (monitoring-logging.blade.php:191)
- [x] Search uses debounce.500ms (search/index.blade.php:103)

---

## 🌐 MANUAL BROWSER TESTS

### Test Environment Setup

1. Start Laravel server:

    ```bash
    php artisan serve
    ```

2. Open browser: http://localhost:8000

3. Open browser DevTools:
    - **Chrome/Edge**: F12 or Ctrl+Shift+I
    - **Firefox**: F12
    - **Safari**: Cmd+Option+I (enable Developer menu first)

---

## TEST 1: Toast Notifications ✅

### Objective

Verify toast notification system works and is accessible.

### Steps

1. **Open Browser Console** (F12 → Console tab)

2. **Test Basic Toast** (copy & paste each line):

    ```javascript
    // Test success toast
    Alpine.store("toast").success("Test successful!");

    // Wait 3 seconds, then test error
    Alpine.store("toast").error("Test error message");

    // Test warning
    Alpine.store("toast").warning("Test warning");

    // Test info
    Alpine.store("toast").info("Test information");
    ```

3. **Expected Results**:
    - [ ] Toasts appear in top-right corner
    - [ ] Each toast has correct color (green/red/yellow/blue)
    - [ ] Icons display correctly
    - [ ] Toasts auto-dismiss after duration
    - [ ] Multiple toasts stack vertically
    - [ ] Smooth slide-in animation from right
    - [ ] Smooth fade-out animation on dismiss

4. **Test Manual Dismiss**:

    ```javascript
    // Show toast that stays longer
    Alpine.store("toast").info("Click X to dismiss", 10000);
    ```

    - [ ] Click the X button
    - [ ] Toast dismisses smoothly

5. **Test Screen Reader Announcement**:
    - Open Elements inspector
    - Find `<div id="toast-announcer">`
    - Trigger toast: `Alpine.store('toast').success('Announced message');`
    - [ ] Verify announcer div briefly shows "Announced message"
    - [ ] Text clears after ~1 second

### Accessibility Check

- [ ] Toasts have `role` attributes (check in Elements)
- [ ] Close button has `<span class="sr-only">Close</span>`
- [ ] Icons have `aria-hidden="true"`

---

## TEST 2: Modal with x-teleport ✅

### Objective

Verify modal teleports to #modal-portal and focus trap works.

### Steps

1. **Navigate to any page with a modal**:
    - Settings page: http://localhost:8000/settings
    - Or any page with delete confirmation

2. **Open DevTools Elements tab**

3. **Trigger a modal** (e.g., click a delete button or edit button)

4. **Verify Teleport**:
    - [ ] In Elements, find `<div id="modal-portal">` (should be near end of `<body>`)
    - [ ] Modal content is **inside** `#modal-portal`, not inline where component was defined
    - [ ] Modal is outside normal page flow (good for z-index)

5. **Test Focus Trap**:
    - [ ] When modal opens, focus moves into modal
    - [ ] Press Tab repeatedly
    - [ ] Focus **stays inside modal** (doesn't jump to background page)
    - [ ] Press Shift+Tab (reverse)
    - [ ] Focus still trapped in modal

6. **Test Background Interaction**:
    - [ ] Try clicking background page elements (shouldn't work)
    - [ ] Background is "inert" (greyed out, non-interactive)
    - [ ] Body scroll is locked (can't scroll background)

7. **Test Escape Key**:
    - [ ] Press Escape key
    - [ ] Modal closes
    - [ ] Focus returns to trigger element

8. **Test Backdrop Click**:
    - [ ] Re-open modal
    - [ ] Click on grey backdrop (not modal content)
    - [ ] Modal closes

### Expected Results

- [ ] No z-index issues (modal always on top)
- [ ] No scroll leakage (background doesn't scroll)
- [ ] Clean DOM structure (modal at end of body)

---

## TEST 3: Accordion with x-collapse ✅

### Objective

Verify accordion animations are smooth with x-collapse.

### Steps

1. **Navigate to**: Settings → Monitoring & Logging  
   URL: http://localhost:8000/settings (then click "Monitoring & Logging" tab)

2. **Find "Method Requirements" section** (accordion with analysis methods)

3. **Test Collapse Animation**:
    - [ ] Click to expand a method
    - [ ] **Watch the animation** (should be smooth height transition)
    - [ ] No jumpy/jerky behavior
    - [ ] Content inside is fully visible when open
    - [ ] Click again to collapse
    - [ ] Smooth animation in reverse

4. **Test Multiple Accordions**:
    - [ ] Expand multiple methods
    - [ ] Each expands independently
    - [ ] No layout shifts
    - [ ] No performance lag

5. **Check DevTools Performance**:
    - Open DevTools → Performance tab
    - Record while clicking accordion
    - [ ] No long tasks (green bars should be small)
    - [ ] Smooth 60fps animation

### Expected Results

- [ ] Silky smooth height animations (no CSS calc hacks visible)
- [ ] No layout thrashing
- [ ] Works on first click (no delay)

---

## TEST 4: Search Debounce ✅

### Objective

Verify search only fires after 500ms pause in typing.

### Steps

1. **Navigate to**: Search page  
   URL: http://localhost:8000/search

2. **Open DevTools Network tab** (F12 → Network)

3. **Clear current results** (refresh page)

4. **Test Debounce**:
    - Type quickly: "sample" (don't pause)
    - [ ] Watch Network tab
    - [ ] Only **ONE** request fires (after you stop typing)
    - [ ] Not 6 requests (one per letter)

5. **Test Pause Detection**:
    - Type "test"
    - Wait 1 second (pause)
    - [ ] Request fires after ~500ms pause
    - Type more: " case"
    - [ ] New request fires only after another pause

6. **Check Console for Events**:
    ```javascript
    // In console, monitor search events
    window.addEventListener("trigger-search", (e) => {
        console.log("Search triggered:", e.detail);
    });
    ```

    - Type in search box
    - [ ] Only one console log per pause

### Expected Results

- [ ] ~80% reduction in API calls
- [ ] Responsive feel (500ms is barely noticeable)
- [ ] No duplicate requests

---

## TEST 5: Enhanced Form Component ✅

### Objective

Verify form fields have proper ARIA attributes.

### Steps

1. **Navigate to**: Register page  
   URL: http://localhost:8000/register

2. **Open DevTools Elements tab**

3. **Inspect Email Input**:
    - Right-click email field → Inspect
    - [ ] Has `id` attribute (e.g., `email`)
    - [ ] Label has matching `for` attribute
    - [ ] Has `aria-describedby` (even if no error)

4. **Trigger Validation Error**:
    - Submit form with invalid email
    - Inspect email input again
    - [ ] Has `aria-invalid="true"`
    - [ ] Error message has `role="alert"`
    - [ ] `aria-describedby` includes error message ID

5. **Check Required Fields**:
    - Inspect required field (e.g., name)
    - [ ] Has `aria-required="true"`
    - [ ] Visual asterisk (\*) with `aria-label="required"` in label

6. **Check Help Text**:
    - If field has help text
    - [ ] Help text has unique ID
    - [ ] Input's `aria-describedby` includes help text ID

### Expected Results

- [ ] All form fields have proper labels
- [ ] Errors are announced to screen readers
- [ ] Required state is programmatically indicated

---

## TEST 6: Keyboard Navigation ⌨️

### Objective

Verify all interactive elements are keyboard accessible.

### Steps

1. **Navigate to Dashboard**: http://localhost:8000/dashboard

2. **Use Only Keyboard** (no mouse):
    - Press Tab repeatedly
    - [ ] Focus indicator visible on each element
    - [ ] Focus order is logical (top → bottom, left → right)
    - [ ] Skip link appears on first Tab (try it)
    - [ ] All buttons/links reachable

3. **Test Dropdown Menus**:
    - Tab to navigation dropdown
    - [ ] Press Enter/Space to open
    - [ ] Tab through menu items
    - [ ] Press Escape to close
    - [ ] Focus returns to trigger

4. **Test Form Navigation**:
    - Navigate to any form page
    - [ ] Tab through all fields
    - [ ] Shift+Tab works in reverse
    - [ ] Enter submits form
    - [ ] Escape cancels (if applicable)

5. **Test Modal**:
    - Tab to button that opens modal
    - Press Enter
    - [ ] Focus moves into modal automatically
    - [ ] Tab cycles only within modal
    - [ ] Escape closes modal
    - [ ] Focus returns to trigger button

### Expected Results

- [ ] No keyboard traps (except modal - intentional)
- [ ] All features work without mouse
- [ ] Focus indicators always visible

---

## TEST 7: Screen Reader Testing 🔊

### Objective

Verify content is announced correctly to screen readers.

### Prerequisites

- **Windows**: NVDA (free) - https://www.nvaccess.org/download/
- **macOS**: VoiceOver (built-in) - Cmd+F5
- **Alternative**: Chrome screen reader extension

### Steps

1. **Start Screen Reader**

2. **Navigate to Dashboard**:
    - [ ] Page title announced
    - [ ] Skip link announced (press it)
    - [ ] Main content landmark announced

3. **Test Toast Announcements**:
    - Trigger action that shows toast (e.g., save settings)
    - [ ] Toast message announced immediately
    - [ ] Type of notification clear (error, success)

4. **Test Form Errors**:
    - Navigate to form
    - Submit with errors
    - [ ] Error summary announced (if present)
    - [ ] Each field error announced when focused
    - [ ] "Invalid" state announced

5. **Test Modal**:
    - Open modal
    - [ ] "Dialog" role announced
    - [ ] Modal title announced
    - [ ] Can navigate within modal
    - [ ] Close button is findable and clear

### Expected Results

- [ ] All content is readable
- [ ] Context changes are announced
- [ ] Navigation landmarks work
- [ ] Forms are understandable

---

## 🎯 QUICK SMOKE TEST (5 minutes)

If you're short on time, run this minimal test:

```bash
# 1. Start server
php artisan serve

# 2. Open http://localhost:8000

# 3. Open browser console, run:
Alpine.store('toast').success('Quick test passed!');

# 4. Navigate to Settings
# 5. Open a modal (any edit/delete button)
# 6. Press Tab - verify focus trap works
# 7. Press Escape - modal closes
# 8. Go to Search page
# 9. Type quickly - verify only 1 network request fires

# If all above work → ✅ VERIFIED
```

---

## 📋 VERIFICATION CHECKLIST

Mark each when tested:

### Core Features

- [ ] Toast notifications display correctly
- [ ] Toast auto-dismiss works
- [ ] Toast screen reader announcements work
- [ ] Modals teleport to #modal-portal
- [ ] Modal focus trap works (x-trap)
- [ ] Modal Escape key works
- [ ] Accordions animate smoothly (x-collapse)
- [ ] Search debounces correctly (500ms)
- [ ] Form fields have ARIA attributes
- [ ] Form errors are accessible

### Accessibility

- [ ] Skip link works
- [ ] Keyboard navigation complete
- [ ] Focus indicators visible
- [ ] Screen reader announces toasts
- [ ] Screen reader can use modals
- [ ] All buttons have accessible names

### Performance

- [ ] No console errors
- [ ] No layout shifts
- [ ] Smooth 60fps animations
- [ ] Debounce reduces network calls
- [ ] No memory leaks (check DevTools Memory)

---

## 🐛 ISSUE REPORTING

If you find issues, document:

1. **What**: Brief description
2. **Where**: URL and component
3. **Steps**: How to reproduce
4. **Expected**: What should happen
5. **Actual**: What actually happens
6. **Browser**: Chrome 120, Firefox 121, etc.
7. **Screenshot**: If visual issue

Example:

```
Issue: Toast doesn't dismiss on X click
Where: Dashboard page, after saving settings
Steps:
  1. Click Save Settings
  2. Toast appears
  3. Click X button
Expected: Toast dismisses
Actual: Nothing happens
Browser: Chrome 120 on Windows 11
```

---

## ✅ SIGN-OFF

Once all tests pass, fill in:

**Tested By**: ******\_\_\_******  
**Date**: ******\_\_\_******  
**Browser**: ******\_\_\_******  
**Result**: ☐ PASS ☐ FAIL  
**Notes**: ******\_\_\_******

---

**Next Step**: If all tests pass, proceed with production deployment! 🚀
