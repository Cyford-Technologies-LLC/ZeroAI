#!/bin/sh

# Set the UID and GID for the application process.
# These values are passed from docker-compose.yml and default to 999.
EXEC_UID=${LOCAL_UID:-999}
EXEC_GID=${LOCAL_GID:-999}

# Log the UID and GID being used
echo "Running as UID: ${EXEC_UID}, GID: ${EXEC_GID}"

# The gosu command will execute the application with the specified user context.
# The `exec` command ensures that the final command replaces the current shell process,
# and we add the CMD arguments correctly.
exec gosu "$EXEC_UID:$EXEC_GID" "$@"
