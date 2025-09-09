#!/bin/bash
# Update portal files from git without rebuilding container

echo "Updating ZeroAI portal from git..."

# Pull latest changes
git pull

# Copy updated files to mounted location (if needed)
if [ -d "/app/app/www" ]; then
    echo "Syncing files from git to mounted location..."
    cp -r /app/app/www/* /app/www/ 2>/dev/null || true
    rm -rf /app/app
fi

# Restart PHP-FPM to reload any changes
if pgrep php-fpm > /dev/null; then
    echo "Restarting PHP-FPM..."
    pkill php-fpm
    service php8.4-fpm start
fi

echo "Portal updated successfully!"
echo "Access at: http://$(hostname -I | awk '{print $1}'):333"