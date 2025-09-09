#!/bin/bash
# Quick fix script - run this in container to sync files without rebuild

echo "Syncing portal files..."

# Copy git files to mounted location
if [ -d "/app/app/www" ]; then
    cp -r /app/app/www/* /app/www/ 2>/dev/null
    echo "Files synced from /app/app/www to /app/www"
fi

# Start services if not running
if ! pgrep php-fpm > /dev/null; then
    service php8.4-fpm start
    echo "PHP-FPM started"
fi

if ! pgrep nginx > /dev/null; then
    nginx
    echo "Nginx started"
fi

# Test portal
curl -s http://127.0.0.1:333 > /dev/null && echo "Portal is working!" || echo "Portal has issues"

echo "Quick fix complete. Portal should be accessible."