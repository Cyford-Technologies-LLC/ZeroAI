#!/bin/bash
# Git wrapper to maintain www-data ownership

# Run git command as www-data
sudo -u www-data git "$@"

# Fix ownership after any git operation
chown -R www-data:www-data /app

exit $?