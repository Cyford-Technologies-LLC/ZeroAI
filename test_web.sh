#!/bin/bash
# Quick test to verify web files exist and are correct

echo "=== Testing ZeroAI Web Interface Setup ==="

# Check if www directory exists
if [ -d "www" ]; then
    echo "✅ www directory exists"
else
    echo "❌ www directory missing"
    exit 1
fi

# Check key files
files=("www/index.php" "www/admin/dashboard.php" "www/admin/agents.php" "www/admin/claude.php")
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
    fi
done

# Check if nginx config points to correct directory
if grep -q "root /app/www;" nginx.conf; then
    echo "✅ nginx config correct"
else
    echo "❌ nginx config issue"
fi

# Test PHP syntax
echo "=== Testing PHP Syntax ==="
php -l www/index.php 2>/dev/null && echo "✅ index.php syntax OK" || echo "❌ index.php syntax error"
php -l www/admin/dashboard.php 2>/dev/null && echo "✅ dashboard.php syntax OK" || echo "❌ dashboard.php syntax error"

echo "=== Test Complete ==="
echo "If all checks pass, the web interface should work after rebuild."