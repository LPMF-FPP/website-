# 🚀 Deployment Checklist - Print & Check Off

**Project:** Pusdokkes Sub-Satker  
**Date:** _______________  
**Deployed by:** _______________  
**Environment:** [ ] Staging [ ] Production  

---

## 📦 PRE-DEPLOYMENT

### Code Quality
- [ ] ✅ All tests passed
- [ ] ✅ Guard audit passed (`npm run audit:guard`)
- [ ] ✅ No critical CSS issues
- [ ] ✅ Git commits up to date
- [ ] ✅ No uncommitted changes

### Build & Assets
- [ ] ✅ `npm run build` executed successfully
- [ ] ✅ Assets compiled (check `public/build/`)
- [ ] ✅ CSS size: ~9 KB gzipped
- [ ] ✅ JS size: ~30 KB gzipped

### Environment
- [ ] `.env` configured for production
- [ ] `APP_ENV=production` set
- [ ] `APP_DEBUG=false` set ⚠️ CRITICAL!
- [ ] `APP_URL` matches domain
- [ ] Database credentials correct
- [ ] Mail settings configured
- [ ] Cache driver set (redis/file)

### Dependencies
- [ ] `composer install --no-dev --optimize-autoloader` run
- [ ] `npm ci --production` run (or assets already built)
- [ ] No dev dependencies in production

---

## 🚀 DEPLOYMENT

### Upload/Deploy
- [ ] Code pushed to production server
- [ ] All files uploaded (exclude: `.env`, `node_modules/`, `.git/`)
- [ ] `public/build/` directory uploaded
- [ ] `storage/` directory writable (775)
- [ ] `bootstrap/cache/` writable (775)

### Database
- [ ] Database backup created
- [ ] `php artisan migrate --force` executed
- [ ] Migrations successful
- [ ] Seed data loaded (if needed)

### Laravel Optimization
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `php artisan event:cache`
- [ ] All caches successful

### File Permissions
- [ ] Ownership: `www-data:www-data` (or appropriate)
- [ ] Directories: 755
- [ ] Files: 644
- [ ] Storage: 775
- [ ] Bootstrap/cache: 775

### Web Server
- [ ] Apache/Nginx configured
- [ ] Document root points to `public/`
- [ ] PHP-FPM running (if applicable)
- [ ] Services restarted
- [ ] `.htaccess` / nginx config active

---

## ✅ POST-DEPLOYMENT

### Health Checks
- [ ] Homepage loads (`https://domain.com`)
- [ ] Returns HTTP 200
- [ ] No 404 errors
- [ ] No console errors (F12)
- [ ] Assets load correctly

### Functionality
- [ ] Login/authentication works
- [ ] Dashboard accessible
- [ ] Theme toggle works (light/dark)
- [ ] Forms submit successfully
- [ ] Database queries work
- [ ] File uploads work (if applicable)
- [ ] Email sending works (send test)
- [ ] Cron jobs running (if applicable)

### Browser Testing
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if applicable)
- [ ] Mobile Chrome
- [ ] Mobile Safari (if applicable)

### Responsive Check
- [ ] Desktop (1920×1080)
- [ ] Laptop (1366×768)
- [ ] Tablet (768×1024)
- [ ] Mobile (375×667)

### Security
- [ ] HTTPS enabled
- [ ] Certificate valid
- [ ] HTTP → HTTPS redirect
- [ ] Security headers present (`X-Frame-Options`, etc.)
- [ ] `robots.txt` configured
- [ ] `sitemap.xml` accessible

### Performance
- [ ] Page load < 3 seconds
- [ ] Time to Interactive < 5 seconds
- [ ] No layout shifts (CLS < 0.1)
- [ ] Images optimized
- [ ] Gzip/Brotli compression active

### Safe Mode v2
- [ ] Theme toggle visible in navbar
- [ ] Light theme renders correctly
- [ ] Dark theme renders correctly
- [ ] Theme persists on refresh
- [ ] No layout shifts on theme change
- [ ] Colors consistent across pages

---

## 📊 MONITORING

### Logs
- [ ] Laravel log accessible (`storage/logs/laravel.log`)
- [ ] No errors in Laravel log
- [ ] Web server logs accessible
- [ ] No errors in web server logs

### Error Tracking
- [ ] Sentry/Bugsnag configured (if applicable)
- [ ] Error tracking receiving events
- [ ] Alert emails configured

### Uptime Monitoring
- [ ] Uptime monitor configured (Pingdom, UptimeRobot, etc.)
- [ ] Status page updated
- [ ] Alert contacts configured

---

## 🔄 ROLLBACK PLAN (if needed)

### Preparation
- [ ] Previous version tag noted: _______________
- [ ] Database backup location: _______________
- [ ] Rollback commands ready

### Execute Rollback
- [ ] Maintenance mode enabled (`php artisan down`)
- [ ] Git reverted to previous commit
- [ ] Database restored from backup
- [ ] Assets rebuilt (`npm run build`)
- [ ] Caches cleared
- [ ] Maintenance mode disabled (`php artisan up`)

---

## 📝 NOTES

**Issues encountered:**
_______________________________________________
_______________________________________________
_______________________________________________

**Solutions applied:**
_______________________________________________
_______________________________________________
_______________________________________________

**Performance metrics:**
- Page load time: _______________ seconds
- Time to Interactive: _______________ seconds
- Lighthouse Performance: _______________ / 100
- Lighthouse Accessibility: _______________ / 100

**Downtime:**
- Start: _______________
- End: _______________
- Total: _______________ minutes

---

## ✅ SIGN-OFF

**Deployed by:**
- Name: _______________
- Signature: _______________
- Date/Time: _______________

**Verified by:**
- Name: _______________
- Signature: _______________
- Date/Time: _______________

**Stakeholder Approval:**
- Name: _______________
- Signature: _______________
- Date/Time: _______________

---

## 🎉 DEPLOYMENT COMPLETE!

**Status:** [ ] Success [ ] Partial [ ] Failed

**Next scheduled deployment:** _______________

**Post-deployment tasks:**
- [ ] Monitor logs for 24 hours
- [ ] Send deployment notification email
- [ ] Update documentation
- [ ] Create post-mortem (if issues)
- [ ] Schedule next release planning

---

**Version deployed:** _______________  
**Commit hash:** _______________  
**Build time:** _______________  
**Total deployment time:** _______________ minutes

**Comments:**
_______________________________________________
_______________________________________________
_______________________________________________

---

✅ **Save this checklist for your records!**
