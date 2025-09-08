#!/bin/sh

# Set the UID and GID for the application process.
EXEC_UID=${LOCAL_UID:-1005}
EXEC_GID=${LOCAL_GID:-1005}

# Log the UID and GID being used
echo "Running as UID: ${EXEC_UID}, GID: ${EXEC_GID}"

# Add the virtual environment's bin directory to the PATH
export PATH="/app/venv/bin:$PATH"

# Check if the group exists, and create it if it doesn't
if ! getent group "$EXEC_GID" >/dev/null; then
    groupadd -g "$EXEC_GID" appgroup
fi

# Check if the user exists, and create it if it doesn't
if ! getent passwd "$EXEC_UID" >/dev/null; then
    useradd --shell /bin/bash -u "$EXEC_UID" -g "$EXEC_GID" -o -c "" -m appuser
fi

# Fix permissions on bind-mounted volumes
chown -R "$EXEC_UID:$EXEC_GID" /app

# Ensure the appuser can access the Docker socket
if [ -S "/var/run/docker.sock" ]; then
    chmod 666 /var/run/docker.sock
fi

# The gosu command will execute the application with the specified user context.
exec gosu "$EXEC_UID:$EXEC_GID" "$@"
