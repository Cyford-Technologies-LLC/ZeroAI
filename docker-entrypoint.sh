#!/bin/sh

# Set the UID and GID for the application process.
# These values are passed from docker-compose.yml and default to 999.
EXEC_UID=${LOCAL_UID:-999}
EXEC_GID=${LOCAL_GID:-999}

# Log the UID and GID being used
echo "Running as UID: ${EXEC_UID}, GID: ${EXEC_GID}"

# Execute the main application command as the specified user.
# The user's UID and GID are set to match the host's via the entrypoint.
exec gosu "$EXEC_UID:$EXEC_GID" "$@"