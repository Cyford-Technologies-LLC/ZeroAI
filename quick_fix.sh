#!/bin/bash
pkill apache2
rm -f /etc/apache2/ports.conf
echo "Listen 333" > /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:333>
DocumentRoot /app/www
DirectoryIndex index.php
</VirtualHost>
EOF
cd /app/www
php -S 0.0.0.0:333