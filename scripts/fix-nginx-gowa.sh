#!/usr/bin/env bash
#
# Apply Nginx reverse proxy fix untuk GOWA v9.
# Fix: strip /gowa prefix sebelum proxy ke backend port 3000
# Run di server production: 192.168.1.59
#
set -euo pipefail

echo "=== GOWA Nginx Reverse Proxy Fix ==="

# 1. Cari file vhost Nginx
VHOST_FILE=""
for f in /etc/nginx/sites-enabled/*; do
    if [ -f "$f" ] || [ -L "$f" ]; then
        VHOST_FILE="$f"
        break
    fi
done

if [ -z "$VHOST_FILE" ]; then
    # Try nginx.conf directly
    if [ -f /etc/nginx/conf.d/default.conf ]; then
        VHOST_FILE="/etc/nginx/conf.d/default.conf"
    elif [ -f /etc/nginx/nginx.conf ]; then
        VHOST_FILE="/etc/nginx/nginx.conf"
    else
        echo "ERROR: Tidak bisa menemukan file konfigurasi Nginx"
        exit 1
    fi
fi

echo "Target: $VHOST_FILE"

# 2. Cek apakah /gowa location sudah ada
if grep -q "location /gowa" "$VHOST_FILE"; then
    echo "Location /gowa sudah ada — akan di-update..."
    
    # Backup
    cp "$VHOST_FILE" "$VHOST_FILE.bak.$(date +%Y%m%d%H%M%S)"
    
    # Hapus location /gowa block yang lama
    awk '
    BEGIN { skip=0; brace=0 }
    /^[[:space:]]*location \/gowa/ { skip=1; brace=0; next }
    skip {
        if ($0 ~ /{/) brace++
        if ($0 ~ /}/) brace--
        if (brace < 0) { skip=0; next }
        next
    }
    { print }
    ' "$VHOST_FILE" > "$VHOST_FILE.tmp"
    
    mv "$VHOST_FILE.tmp" "$VHOST_FILE"
else
    cp "$VHOST_FILE" "$VHOST_FILE.bak.$(date +%Y%m%d%H%M%S)"
    echo "Menambahkan location /gowa block..."
fi

# 3. Tambah location block baru (sebelum closing server block)
NGINX_LOCATION='
    # GOWA v9 WhatsApp API — reverse proxy
    location /gowa/ {
        rewrite ^/gowa/(.*)$ /$1 break;
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;

        # v9 requires Upgrade for WebSocket (/ws)
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        proxy_buffering off;
        proxy_read_timeout 120s;
        proxy_send_timeout 120s;
    }
'

# Insert sebelum penutup server block (baris terakhir })
sed -i "\$i\\${NGINX_LOCATION}" "$VHOST_FILE"

# 4. Validate & reload
echo "Validating Nginx config..."
nginx -t

echo "Reloading Nginx..."
systemctl reload nginx

echo ""
echo "=== Fix selesai ==="
echo "Cek: curl -s -u '<GOWA_BASIC_AUTH>' http://lpmf.web.id/gowa/app/info"
