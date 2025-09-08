#!/bin/bash
# Auto-start PHP portal when container starts

echo "Starting ZeroAI PHP Portal..."

# Install PHP and Apache if not installed
if ! command -v php &> /dev/null; then
    echo "Installing PHP and Apache..."
    apt-get update
    apt-get install -y apache2 php libapache2-mod-php php-sqlite3
    a2enmod rewrite
fi

# Configure Apache for port 333
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:333>
    DocumentRoot /app/www
    DirectoryIndex index.php
    
    <Directory /app/www>
        AllowOverride All
        Require all granted
    </Directory>
    
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /index.php [QSA,L]
</VirtualHost>
EOF

# Add Listen directive
echo "Listen 333" >> /etc/apache2/ports.conf

# Set ServerName
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Create database directory
mkdir -p /app/data

# Start Apache
echo "Starting Apache on port 333..."
apache2ctl -D FOREGROUND