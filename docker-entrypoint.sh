#!/bin/sh

# Source the user's profile to ensure PATH is correctly set
. "$HOME/.profile"

# Change ownership of the config directory to the appuser
chown -R appuser:appuser /app/
#chown -R appuser:appuser /app/config

# Execute the original command
exec "$@"
