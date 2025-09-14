#!/bin/bash
# Fix database permissions after git operations
chmod 666 /app/data/zeroai.db 2>/dev/null || true
chmod 777 /app/data 2>/dev/null || true
chown -R www-data:www-data /app/data 2>/dev/null || true
echo "Database permissions fixed"