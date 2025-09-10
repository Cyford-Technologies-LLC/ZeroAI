#!/bin/bash
# Git wrapper to maintain www-data ownership

# Run git command as www-data
sudo -u www-data git "$@"

# Fix ownership after git operations, but skip read-only files
find /app -type f -writable -exec chown www-data:www-data {} + 2>/dev/null || true
find /app -type d -writable -exec chown www-data:www-data {} + 2>/dev/null || true

exit $?