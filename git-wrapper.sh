#!/bin/bash
# Git wrapper to maintain www-data ownership

# Run git command as root (since we need to pull from remote)
/usr/bin/git "$@"

# Fix ownership after git operations for key directories
chown -R www-data:www-data /app/src /app/config /app/examples /app/www 2>/dev/null || true
chmod -R 755 /app/src /app/config /app/examples /app/www 2>/dev/null || true

exit $?