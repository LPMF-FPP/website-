# Browsershot PDF Environment Setup - Linux Checklist

**Last Updated:** 22 Desember 2025  
**Target OS:** Ubuntu 20.04/22.04/24.04, Debian 11/12, CentOS 8+

---

## 📋 Prerequisites Checklist

### ✅ 1. Node.js Installation

**Required Version:** Node.js 16+ (Recommended: 18 LTS or 20 LTS)

#### Ubuntu/Debian

```bash
# Check if Node.js is installed
node --version

# If not installed or version < 16, install via NodeSource
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify installation
node --version  # Should show v20.x.x
npm --version   # Should show 10.x.x

# Get full path for .env
which node  # Copy this path to BROWSERSHOT_NODE_BINARY
which npm   # Copy this path to BROWSERSHOT_NPM_BINARY
```

**Expected Paths:**
- Node: `/usr/bin/node`
- NPM: `/usr/bin/npm`

---

### ✅ 2. Puppeteer Installation

**Required:** Puppeteer package (installed via Composer)

```bash
# Navigate to project root
cd /home/lpmf-dev/website-

# Install Puppeteer dependencies via Composer (if not already)
composer require spatie/browsershot

# Install Puppeteer node package
npm install puppeteer --save

# Or if using package.json
npm install
```

**Verify Puppeteer:**
```bash
npm list puppeteer
# Should show: puppeteer@21.x.x or similar
```

---

### ✅ 3. Chrome/Chromium Installation

**Options:**
- Google Chrome (recommended for production)
- Chromium Browser (lighter, good for Docker)

#### Option A: Google Chrome (Ubuntu/Debian)

```bash
# Download and install Google Chrome
wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo dpkg -i google-chrome-stable_current_amd64.deb

# Fix dependencies if needed
sudo apt-get install -f

# Verify installation
google-chrome --version

# Get path for .env
which google-chrome  # Usually /usr/bin/google-chrome
```

#### Option B: Chromium Browser (Ubuntu/Debian)

```bash
# Install Chromium
sudo apt-get update
sudo apt-get install -y chromium-browser

# Verify
chromium-browser --version

# Get path
which chromium-browser  # Usually /usr/bin/chromium-browser
```

#### Option C: For Docker/Alpine Linux

```bash
# In Dockerfile
RUN apk add --no-cache \
    chromium \
    nss \
    freetype \
    harfbuzz \
    ca-certificates \
    ttf-freefont \
    nodejs \
    npm

# Chrome path in Alpine: /usr/bin/chromium-browser
```

**Expected Paths:**
- Ubuntu/Debian: `/usr/bin/google-chrome` or `/usr/bin/chromium-browser`
- Alpine/Docker: `/usr/bin/chromium-browser`
- CentOS: `/usr/bin/chromium-browser`

---

### ✅ 4. Chrome Dependencies

Chrome/Chromium requires additional system libraries:

#### Ubuntu/Debian

```bash
sudo apt-get install -y \
    libx11-6 \
    libx11-xcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libgbm1 \
    libasound2 \
    libpangocairo-1.0-0 \
    libgtk-3-0 \
    fonts-liberation \
    xdg-utils
```

#### CentOS/RHEL

```bash
sudo yum install -y \
    libX11 \
    libX11-xcb \
    libXcomposite \
    libXcursor \
    libXdamage \
    libXext \
    libXi \
    libXtst \
    cups-libs \
    libXScrnSaver \
    libXrandr \
    alsa-lib \
    pango \
    atk \
    at-spi2-atk \
    gtk3 \
    nss
```

---

### ✅ 5. Fonts Installation (For PDF Rendering)

**Important:** Install fonts to avoid rendering issues with special characters.

```bash
# Basic fonts
sudo apt-get install -y \
    fonts-liberation \
    fonts-dejavu-core \
    fontconfig

# Additional fonts for better coverage
sudo apt-get install -y \
    fonts-noto \
    fonts-noto-cjk \
    fonts-noto-color-emoji \
    fonts-freefont-ttf

# Refresh font cache
fc-cache -f -v

# Verify fonts
fc-list | grep -i "liberation"
```

---

### ✅ 6. Environment Configuration

Create or update `.env` file:

```bash
# Navigate to project root
cd /home/lpmf-dev/website-

# Copy from example if not exists
cp .env.example .env

# Edit .env
nano .env
```

**Add/Update these lines:**

```dotenv
# Browsershot Configuration
BROWSERSHOT_NODE_BINARY=/usr/bin/node
BROWSERSHOT_NPM_BINARY=/usr/bin/npm
BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome
BROWSERSHOT_NO_SANDBOX=false
BROWSERSHOT_TIMEOUT=60
```

**For Docker environments, set:**
```dotenv
BROWSERSHOT_NO_SANDBOX=true
BROWSERSHOT_CHROME_PATH=/usr/bin/chromium-browser
```

---

### ✅ 7. Permissions & Security

#### Standard Linux Server

```bash
# Ensure web server user can execute Chrome
# (Typically www-data, nginx, or apache)

# Check current user
whoami

# Test Chrome execution
google-chrome --version
# or
chromium-browser --version

# If permission denied, check executable permissions
ls -la /usr/bin/google-chrome
# Should show: -rwxr-xr-x
```

#### Docker/Container Environment

**Set no-sandbox mode:**
```dotenv
BROWSERSHOT_NO_SANDBOX=true
```

**Dockerfile additions:**
```dockerfile
# Add Chrome dependencies
RUN apt-get update && apt-get install -y \
    chromium-browser \
    fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

# Set Chrome path
ENV BROWSERSHOT_CHROME_PATH=/usr/bin/chromium-browser
ENV BROWSERSHOT_NO_SANDBOX=true
```

---

### ✅ 8. Testing Installation

Run the built-in test command:

```bash
php artisan tinker --execute="
\$service = new \App\Services\PdfRenderService();
\$test = \$service->testConfiguration();
print_r(\$test);
"
```

**Expected Output:**
```
Array
(
    [success] => 1
    [checks] => Array
        (
            [node] => Array
                (
                    [binary] => /usr/bin/node
                    [available] => 1
                    [version] => v20.11.0
                )
            [npm] => Array
                (
                    [binary] => /usr/bin/npm
                    [available] => 1
                    [version] => 10.2.4
                )
            [chrome] => Array
                (
                    [path] => /usr/bin/google-chrome
                    [available] => 1
                    [version] => Google Chrome 120.0.6099.109
                )
        )
    [config] => Array
        (
            [no_sandbox] => 
            [timeout] => 60
        )
)
```

---

### ✅ 9. Test PDF Generation

```bash
php artisan tinker --execute="
\$service = new \App\Services\PdfRenderService();
\$html = '<html><body><h1>Test PDF</h1><p>This is a test document.</p></body></html>';
\$pdf = \$service->htmlToPdf(\$html);
file_put_contents('/tmp/test-browsershot.pdf', \$pdf);
echo 'PDF saved to /tmp/test-browsershot.pdf' . PHP_EOL;
echo 'Size: ' . strlen(\$pdf) . ' bytes' . PHP_EOL;
"
```

**Verify PDF:**
```bash
# Check file exists
ls -lh /tmp/test-browsershot.pdf

# Open PDF (if GUI available)
xdg-open /tmp/test-browsershot.pdf

# Or copy to local machine for inspection
```

---

## 🐛 Troubleshooting

### Issue: "Command not found: node"

**Solution:**
```bash
# Find Node.js location
find /usr -name node 2>/dev/null

# Add to PATH or use full path in .env
export PATH=$PATH:/usr/bin
```

---

### Issue: "Error: Failed to launch chrome!"

**Possible Causes:**
1. Chrome not installed
2. Missing dependencies
3. Permission issues
4. Running as root without --no-sandbox

**Solutions:**

```bash
# 1. Verify Chrome is installed
google-chrome --version

# 2. Install missing dependencies
sudo apt-get install -y libgbm1 libasound2

# 3. Check permissions
ls -la /usr/bin/google-chrome

# 4. Enable no-sandbox mode in .env
BROWSERSHOT_NO_SANDBOX=true
```

---

### Issue: "Running as root without --no-sandbox is not supported"

**Solution:**
```dotenv
# In .env
BROWSERSHOT_NO_SANDBOX=true
```

---

### Issue: Fonts not rendering correctly

**Solution:**
```bash
# Install more fonts
sudo apt-get install -y fonts-noto fonts-liberation

# Refresh font cache
fc-cache -f -v

# Test fonts
fc-list | grep Arial
```

---

### Issue: Timeout errors

**Solution:**
```dotenv
# Increase timeout in .env
BROWSERSHOT_TIMEOUT=120
```

---

### Issue: "ECONNREFUSED" or network errors

**Solution:**
```bash
# Check if Chrome is trying to connect to internet
# Add to .env to disable network features
BROWSERSHOT_NO_SANDBOX=true

# Or in controller, add:
$browsershot->addChromiumArguments(['--disable-web-security']);
```

---

## 📊 System Requirements Summary

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **Node.js** | 16.x | 20.x LTS |
| **NPM** | 8.x | 10.x |
| **Chrome/Chromium** | 90+ | Latest stable |
| **RAM** | 2 GB | 4 GB+ |
| **Disk Space** | 500 MB | 1 GB+ (for fonts) |
| **PHP** | 8.1 | 8.2+ |

---

## 🐳 Docker Example

### Dockerfile

```dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    nodejs \
    npm \
    chromium \
    fonts-liberation \
    fonts-noto \
    libgbm1 \
    libasound2 \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies
RUN npm install

# Set environment
ENV BROWSERSHOT_NODE_BINARY=/usr/bin/node
ENV BROWSERSHOT_NPM_BINARY=/usr/bin/npm
ENV BROWSERSHOT_CHROME_PATH=/usr/bin/chromium
ENV BROWSERSHOT_NO_SANDBOX=true
ENV BROWSERSHOT_TIMEOUT=60

# Expose port
EXPOSE 9000

CMD ["php-fpm"]
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build: .
    environment:
      - BROWSERSHOT_NODE_BINARY=/usr/bin/node
      - BROWSERSHOT_NPM_BINARY=/usr/bin/npm
      - BROWSERSHOT_CHROME_PATH=/usr/bin/chromium
      - BROWSERSHOT_NO_SANDBOX=true
    volumes:
      - .:/var/www
    security_opt:
      - seccomp:unconfined
```

---

## ✅ Final Verification Checklist

- [ ] Node.js installed (v16+)
- [ ] NPM installed (v8+)
- [ ] Chrome/Chromium installed
- [ ] All Chrome dependencies installed
- [ ] Fonts installed
- [ ] `.env` configured with correct paths
- [ ] `testConfiguration()` returns success
- [ ] Test PDF generation works
- [ ] No permission errors
- [ ] Timeout value appropriate for your content

---

## 📝 Quick Copy-Paste Setup (Ubuntu 22.04/24.04)

```bash
# 1. Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# 2. Install Chrome
wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo dpkg -i google-chrome-stable_current_amd64.deb
sudo apt-get install -f

# 3. Install Chrome dependencies
sudo apt-get install -y \
    libgbm1 libasound2 fonts-liberation fonts-noto

# 4. Install Puppeteer
cd /home/lpmf-dev/website-
npm install puppeteer

# 5. Configure .env
cat >> .env << 'EOF'

# Browsershot Configuration
BROWSERSHOT_NODE_BINARY=/usr/bin/node
BROWSERSHOT_NPM_BINARY=/usr/bin/npm
BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome
BROWSERSHOT_NO_SANDBOX=false
BROWSERSHOT_TIMEOUT=60
EOF

# 6. Test
php artisan tinker --execute="
\$service = new \App\Services\PdfRenderService();
print_r(\$service->testConfiguration());
"
```

---

**Support:** Check Laravel logs at `storage/logs/laravel.log` for detailed Browsershot errors.
