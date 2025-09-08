#!/bin/bash
# Fix Apache configuration

echo "Fixing Apache configuration..."

# Remove duplicate Listen directives
sed -i '/Listen 333/d' /etc/apache2/ports.conf
echo "Listen 333" >> /etc/apache2/ports.conf

# Simple Apache config
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:333>
    DocumentRoot /app/www
    DirectoryIndex index.php
    
    <Directory /app/www>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

# Start Apache
echo "Starting Apache..."
apache2ctl -D FOREGROUND