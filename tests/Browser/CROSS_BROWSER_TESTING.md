# Cross-Browser Testing Configuration

## Setup Instructions

### 1. Chrome (Default)

Already configured. No additional setup needed.

```bash
php artisan dusk
```

### 2. Firefox

**Install GeckoDriver:**

```bash
# macOS
brew install geckodriver

# Linux
wget https://github.com/mozilla/geckodriver/releases/download/v0.34.0/geckodriver-v0.34.0-linux64.tar.gz
tar -xvzf geckodriver-v0.34.0-linux64.tar.gz
sudo mv geckodriver /usr/local/bin/
```

**Run tests:**

```bash
TEST_BROWSER=firefox php artisan dusk
```

### 3. Edge (Chromium-based)

Edge uses the same ChromeDriver as Chrome. No additional configuration needed.

```bash
# Edge tests run the same as Chrome
php artisan dusk
```

## Visual Regression Testing

### Using Browser Screenshots

Dusk includes built-in screenshot functionality:

```bash
# Screenshots are saved to tests/Browser/screenshots/
php artisan dusk tests/Browser/Visual/VisualRegressionTest.php
```

### Advanced Visual Regression (Optional)

For pixel-perfect comparison, consider:

1. **Percy (Recommended)**

    ```bash
    composer require percy/percy-php-selenium
    export PERCY_TOKEN=your_token
    npx percy snapshot tests/Browser/screenshots
    ```

2. **BackstopJS**
    ```bash
    npm install -g backstopjs
    backstop init
    # Configure backstop.json with test URLs
    backstop test
    ```

## Mobile Testing

Mobile viewport tests use Chrome's device emulation:

```bash
php artisan dusk tests/Browser/Mobile/MobileResponsiveTest.php
```

Common mobile viewports:

- iPhone SE: 375x667
- iPhone 12 Pro: 390x844
- iPad: 768x1024
- Samsung Galaxy S21: 360x800

## Browser Compatibility Matrix

| Browser | Status         | Driver             | Notes                    |
| ------- | -------------- | ------------------ | ------------------------ |
| Chrome  | ✅ Supported   | ChromeDriver 143.x | Default browser          |
| Firefox | ✅ Supported   | GeckoDriver 0.34.x | Set TEST_BROWSER=firefox |
| Edge    | ✅ Supported   | ChromeDriver 143.x | Same as Chrome           |
| Safari  | ⚠️ Manual Only | N/A                | Not automated            |

## Running All Cross-Browser Tests

```bash
# Chrome (default)
php artisan dusk

# Firefox
TEST_BROWSER=firefox php artisan dusk

# Edge (same as Chrome)
php artisan dusk
```

## CI/CD Integration

Add to GitHub Actions:

```yaml
- name: Run Dusk Tests (Chrome)
  run: php artisan dusk

- name: Run Dusk Tests (Firefox)
  run: |
      sudo apt-get install -y firefox-geckodriver
      TEST_BROWSER=firefox php artisan dusk
```
