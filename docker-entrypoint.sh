#!/bin/sh

# Set defaults for UID and GID for portability.
export EXEC_UID=${LOCAL_UID:-999}
export EXEC_GID=${LOCAL_GID:-999}

# **FIX:** Ensure the entire /app directory is owned by the appuser
chown -R "$LOCAL_UID":"$LOCAL_GID" /app

# The DOCKER_HOST environment variable should be set to the path of the mounted socket.
# Ensure that the appuser can access the Docker socket
#if [ -S "/var/run/docker.sock" ]; then
#    chmod 666 /var/run/docker.sock
#fi

echo "Running as UID: ${EXEC_UID}, GID: ${EXEC_GID}"

# Execute the main application command as the appuser
exec gosu "$EXEC_UID:$EXEC_GID" "$@"
