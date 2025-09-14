#!/bin/bash
# Fix all git permission issues

echo "Fixing git permissions..."

# Take ownership of entire repo
chown -R $(whoami):$(whoami) .

# Make everything writable
chmod -R 755 .

# Fix specific problem areas
chmod -R 777 www/
chmod -R 777 data/ 2>/dev/null || true
chmod -R 777 logs/ 2>/dev/null || true

# Reset git index
git reset --hard HEAD
git clean -fd

echo "Git permissions fixed. You can now pull/reset normally."