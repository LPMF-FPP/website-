# Deployment Guide - Pusdokkes Sub-Satker

**Status:** ✅ Ready for Production  
**Last Audit:** 06/10/2025  
**Build Status:** ✅ Passed  

---

## 🎯 Pre-Deployment Summary

```
┌────────────────────────────────────────────────┐
│              DEPLOYMENT STATUS                 │
├────────────────────────────────────────────────┤
│ ✅ Guard Check       │ PASSED (0 violations)  │
│ ✅ Build Assets      │ PASSED (1.11s)         │
│ ✅ Safe Mode v2      │ COMPLIANT              │
│ ✅ Theme System      │ 100% Parity            │
├────────────────────────────────────────────────┤
│ 🚀 READY TO DEPLOY                             │
└────────────────────────────────────────────────┘
```

**Assets Built:**
- CSS: 53.53 KB → 9.19 KB (gzipped)
- JS: 80.59 KB → 30.19 KB (gzipped)
- Total: ~40 KB gzipped ✅ Excellent

---

## 📋 Pre-Deployment Checklist

### 1. ✅ Code Quality & Audits

```bash
# Run final audits
npm run audit:guard        # ✅ PASSED
npm run audit:cascade      # ✅ 0 critical issues
npm run audit:contrast     # ✅ Perfect parity

# Build assets
npm run build              # ✅ PASSED
```

**Status:** ✅ All checks passed

### 2. Environment Configuration

**Update `.env` for production:**

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

# Cache
CACHE_DRIVER=redis     # or file
SESSION_DRIVER=redis   # or file
QUEUE_CONNECTION=redis # or database

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
```

**Security checklist:**
- [ ] `APP_DEBUG=false` ✅ Critical!
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secure
- [ ] Mail credentials configured
- [ ] All sensitive data in `.env`, not committed

### 3. Laravel Optimization

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run database migrations (if needed)
php artisan migrate --force

# Seed production data (if needed)
php artisan db:seed --class=ProductionSeeder
```

### 4. File Permissions (Linux/Unix)

```bash
# Set correct ownership
chown -R www-data:www-data /path/to/project

# Set directory permissions
find /path/to/project -type d -exec chmod 755 {} \;

# Set file permissions
find /path/to/project -type f -exec chmod 644 {} \;

# Storage & cache writable
chmod -R 775 storage bootstrap/cache
```

### 5. Web Server Configuration

#### Apache (.htaccess already included)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

#### Nginx (example config)

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /path/to/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 🚀 Deployment Steps

### Option A: Manual Deployment

**On your local machine:**

```bash
# 1. Final commit
git add .
git commit -m "Production release v1.0

- Implemented Safe Mode v2 theme system
- Passed all frontend audits
- Built production assets
- Ready for deployment

Co-authored-by: factory-droid[bot] <138933559+factory-droid[bot]@users.noreply.github.com>"

# 2. Push to repository
git push origin main

# 3. Create release tag (optional)
git tag -a v1.0.0 -m "Production release v1.0.0"
git push origin v1.0.0
```

**On production server:**

```bash
# 1. Pull latest code
cd /path/to/project
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --production

# 3. Build assets (if not committed)
npm run build

# 4. Run migrations
php artisan migrate --force

# 5. Clear & cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Set permissions
chmod -R 775 storage bootstrap/cache

# 7. Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### Option B: Automated Deployment (Recommended)

**Using Laravel Deployer or similar:**

```bash
# Deploy to production
dep deploy production

# Or using Envoyer, Forge, etc.
# Push to main branch triggers auto-deployment
```

### Option C: Shared Hosting (cPanel)

1. **Build assets locally:**
   ```bash
   npm run build
   composer install --no-dev
   ```

2. **Upload via FTP/SFTP:**
   - Upload all files to `public_html/` or subdirectory
   - **DO NOT upload:** `.env`, `node_modules/`, `.git/`
   - **DO upload:** `public/build/` (assets)

3. **Configure .env:**
   - Copy `.env.example` to `.env` on server
   - Edit via cPanel File Manager or text editor
   - Set production values

4. **Run artisan commands:**
   ```bash
   # Via SSH
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   ```

---

## ✅ Post-Deployment Verification

### 1. Health Check

```bash
# Check homepage
curl -I https://your-domain.com

# Expected: HTTP/2 200

# Check assets
curl -I https://your-domain.com/build/assets/app-CXDpL9bK.js

# Expected: HTTP/2 200
```

### 2. Functionality Tests

**Manual checks:**
- [ ] Homepage loads correctly
- [ ] Login/authentication works
- [ ] Dashboard accessible
- [ ] Theme toggle works (light/dark)
- [ ] Forms submit successfully
- [ ] Database queries work
- [ ] File uploads work (if applicable)
- [ ] Email sending works (test)

**Browser checks:**
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if applicable)
- [ ] Mobile responsive

### 3. Performance Check

```bash
# Run Lighthouse (if server accessible)
npm run audit:lh

# Or use online tools:
# - Google PageSpeed Insights
# - GTmetrix
# - WebPageTest
```

**Expected scores:**
- Performance: 75+
- Accessibility: 90+
- Best Practices: 85+
- SEO: 80+

### 4. Security Check

**SSL/HTTPS:**
- [ ] HTTPS enabled
- [ ] Certificate valid
- [ ] HTTP redirects to HTTPS
- [ ] HSTS enabled (optional but recommended)

**Headers check:**
```bash
curl -I https://your-domain.com

# Should include:
# X-Frame-Options: SAMEORIGIN
# X-Content-Type-Options: nosniff
```

### 5. Error Monitoring

**Check logs:**
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Web server logs
tail -f /var/log/nginx/error.log
# or
tail -f /var/log/apache2/error.log
```

**Set up error tracking (recommended):**
- Sentry
- Bugsnag
- Laravel Telescope (staging only)

---

## 🔥 Rollback Plan

**If something goes wrong:**

### Quick Rollback

```bash
# 1. Revert to previous commit
git revert HEAD
git push origin main

# 2. Or checkout previous tag
git checkout v0.9.0

# 3. Rebuild assets
npm run build
composer install

# 4. Clear caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# 5. Restart services
sudo systemctl restart php-fpm nginx
```

### Emergency Maintenance Mode

```bash
# Enable maintenance mode
php artisan down

# With custom message
php artisan down --message="Scheduled maintenance. Back soon!" --retry=60

# Allow specific IPs
php artisan down --allow=YOUR_IP_ADDRESS

# Disable maintenance mode
php artisan up
```

---

## 📊 Monitoring & Maintenance

### Daily Checks

```bash
# Check logs for errors
tail -f storage/logs/laravel.log | grep ERROR

# Monitor disk space
df -h

# Monitor database
php artisan db:show
```

### Weekly Maintenance

```bash
# Update dependencies (staging first!)
composer update
npm update

# Run audits
npm run audit:critical

# Backup database
php artisan backup:run
```

### Monthly Maintenance

```bash
# Full security audit
npm audit
composer audit

# Performance audit
npm run audit:all

# Database optimization
php artisan db:optimize
```

---

## 🐛 Common Issues & Solutions

### Issue: "500 Internal Server Error"

**Solution:**
```bash
# Check logs
tail -f storage/logs/laravel.log

# Common fixes:
php artisan cache:clear
php artisan config:clear
chmod -R 775 storage bootstrap/cache
```

### Issue: "Mix/Vite manifest not found"

**Solution:**
```bash
# Rebuild assets
npm run build

# Ensure public/build/ exists
ls -la public/build/
```

### Issue: "CSRF token mismatch"

**Solution:**
```bash
# Clear sessions
php artisan session:flush

# Check SESSION_DRIVER in .env
# Ensure session table exists (if using database)
php artisan session:table
php artisan migrate
```

### Issue: "Theme not switching"

**Solution:**
```bash
# Check browser console for JS errors
# Ensure theme-toggle-v2.js is loaded
curl https://your-domain.com/scripts/theme-toggle-v2.js

# Check localStorage
# Open browser DevTools → Application → Local Storage
# Should see "pd-theme" key
```

### Issue: "Styles not applying"

**Solution:**
```bash
# Check if CSS files are loaded
curl -I https://your-domain.com/build/assets/app-Cl90uK7D.css

# Clear browser cache
# Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)

# Check if Safe Mode is active
# DevTools → Elements → <html> should have data-pd-safe attribute
```

---

## 📞 Support & Resources

**Documentation:**
- Laravel: https://laravel.com/docs
- Vite: https://vitejs.dev/
- Tailwind CSS: https://tailwindcss.com/docs

**Audit System:**
- User Guide: `report/README.md`
- Audit Results: `report/AUDIT-RESULTS-SUMMARY.md`
- Run audits: `npm run audit:critical`

**Safe Mode v2:**
- Documentation: `SAFE-MODE-V2.md`
- Quick Start: `QUICK-START-SAFE-MODE.md`

**Need Help?**
- Check `storage/logs/laravel.log`
- Review audit reports in `report/`
- Check browser console for JS errors

---

## 🎉 Deployment Complete!

**Checklist:**
- [x] All audits passed
- [x] Assets built for production
- [x] Environment configured
- [x] Laravel optimized
- [x] Deployed to server
- [x] Post-deployment verified
- [x] Monitoring in place

**Next Steps:**
1. Monitor logs for first 24 hours
2. Collect user feedback
3. Run weekly `npm run audit:critical`
4. Plan next release cycle

---

**Deployed by:** [Your Name]  
**Date:** [Current Date]  
**Version:** v1.0.0  
**Build:** Production  
**Status:** ✅ Live

**Happy Deploying!** 🚀
