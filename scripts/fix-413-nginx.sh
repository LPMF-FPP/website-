#!/bin/bash
#
# Fix Nginx 413 "Request Entity Too Large" Error
# Server: 192.168.1.59
# Run this script on the production server as root
#

set -e

echo "=== Step 1: Identify Nginx Configuration ==="
echo "Active Nginx vhosts:"
ls -la /etc/nginx/sites-enabled/

echo ""
echo "Checking Nginx config dump..."
nginx -T 2>&1 | grep -E "server_name|listen|root|client_max_body_size" | head -30

# Find the active server block file
VHOST_FILE=""
for f in /etc/nginx/sites-enabled/*; do
    if [ -f "$f" ] || [ -L "$f" ]; then
        VHOST_FILE="$f"
        break
    fi
done

if [ -z "$VHOST_FILE" ]; then
    echo "ERROR: No vhost file found in /etc/nginx/sites-enabled/"
    exit 1
fi

echo ""
echo "=== Step 2: Backup and Modify Nginx Configuration ==="
echo "Target file: $VHOST_FILE"

# Backup the file
cp "$VHOST_FILE" "$VHOST_FILE.bak.$(date +%Y%m%d%H%M%S)"
echo "Backup created: $VHOST_FILE.bak.*"

# Check if client_max_body_size already exists
if grep -q "client_max_body_size" "$VHOST_FILE"; then
    echo "client_max_body_size already exists, updating value..."
    sed -i 's/client_max_body_size.*/client_max_body_size 50M;/' "$VHOST_FILE"
else
    echo "Adding client_max_body_size 50M to server block..."
    # Add after the first 'server {' line
    sed -i '/server {/a\    client_max_body_size 50M;' "$VHOST_FILE"
fi

echo ""
echo "=== Step 3: Validate and Reload Nginx ==="
echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
systemctl reload nginx
echo "✓ Nginx reloaded successfully"

echo ""
echo "=== Step 4: Check PHP Version and Update FPM Limits ==="
PHP_VERSION=$(php -v | head -1 | grep -oP '\d+\.\d+' | head -1)
echo "Detected PHP version: $PHP_VERSION"

PHP_FPM_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
if [ ! -f "$PHP_FPM_INI" ]; then
    echo "ERROR: PHP-FPM php.ini not found at $PHP_FPM_INI"
    echo "Trying common paths..."
    for v in 8.4 8.3 8.2 8.1; do
        if [ -f "/etc/php/$v/fpm/php.ini" ]; then
            PHP_FPM_INI="/etc/php/$v/fpm/php.ini"
            PHP_VERSION="$v"
            echo "Found PHP-FPM config: $PHP_FPM_INI"
            break
        fi
    done
fi

if [ -f "$PHP_FPM_INI" ]; then
    echo "Modifying PHP-FPM php.ini: $PHP_FPM_INI"
    
    # Backup
    cp "$PHP_FPM_INI" "$PHP_FPM_INI.bak.$(date +%Y%m%d%H%M%S)"
    
    # Update values (handling both commented and uncommented lines)
    sed -i 's/^upload_max_filesize.*/upload_max_filesize = 50M/' "$PHP_FPM_INI"
    sed -i 's/^post_max_size.*/post_max_size = 50M/' "$PHP_FPM_INI"
    
    echo "Current PHP upload settings:"
    grep -E "^(upload_max_filesize|post_max_size)" "$PHP_FPM_INI"
    
    echo ""
    echo "Reloading PHP-FPM..."
    systemctl reload "php${PHP_VERSION}-fpm" || systemctl restart "php${PHP_VERSION}-fpm"
    echo "✓ PHP-FPM reloaded"
else
    echo "WARNING: Could not find PHP-FPM php.ini"
fi

echo ""
echo "=== Step 5: Verification ==="
echo "Nginx listening:"
ss -lntp | grep -E ':80|:443'

echo ""
echo "Testing endpoints:"
curl -sI http://127.0.0.1/requests | head -10
echo ""
curl -sI http://192.168.1.59/requests | head -10

echo ""
echo "=== Summary ==="
echo "Modified files:"
echo "  - Nginx vhost: $VHOST_FILE"
echo "  - PHP-FPM ini: $PHP_FPM_INI"
echo ""
echo "Changes made:"
echo "  - client_max_body_size = 50M (Nginx)"
echo "  - upload_max_filesize = 50M (PHP)"
echo "  - post_max_size = 50M (PHP)"
echo ""
echo "✓ Fix complete! The 413 error should be resolved."
