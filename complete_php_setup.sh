#!/bin/bash
# Complete PHP portal setup - kills everything and starts fresh

echo "Killing all services on port 333..."
fuser -k 333/tcp 2>/dev/null
pkill -f "python.*333" 2>/dev/null
pkill -f "apache.*333" 2>/dev/null
pkill -f "gunicorn" 2>/dev/null
pkill apache2 2>/dev/null
sleep 3

echo "Installing PHP if needed..."
apt-get update -qq
apt-get install -y php php-sqlite3 2>/dev/null

echo "Creating database directory..."
mkdir -p /app/data
chmod 777 /app/data

echo "Setting up PHP portal files..."
cd /app/www

# Create htaccess for clean URLs
cat > .htaccess << 'EOF'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
EOF

# Fix database path in config
sed -i 's|/app/data/zeroai.db|/app/data/zeroai.db|g' config/database.php

echo "Starting PHP server on port 333..."
php -S 0.0.0.0:333 &

sleep 2
echo "PHP Portal is running!"
echo "Access: http://74.208.234.43:333/admin"
echo "Login: admin / admin123"

# Keep script running
wait