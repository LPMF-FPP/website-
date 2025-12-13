@echo off
REM Sync Safe Mode v2 assets to public directory
REM Run this after modifying files in styles/ or scripts/

echo ========================================
echo  Syncing Safe Mode v2 Assets
echo ========================================
echo.

echo Copying CSS files...
copy /Y "styles\pd.ultrasafe.tokens.css" "public\styles\"
copy /Y "styles\pd.framework-bridge.css" "public\styles\"
copy /Y "styles\pd-safe-layers.css" "public\styles\"

echo.
echo Copying JS files...
copy /Y "scripts\theme-toggle-v2.js" "public\scripts\"

echo.
echo ========================================
echo  Sync Complete!
echo ========================================
echo.
echo Files updated in public/ directory:
dir /B "public\styles\pd*.css"
dir /B "public\scripts\theme-toggle*.js"
echo.

pause
