#!/bin/sh

# Set defaults for UID and GID for portability.
export LOCAL_UID=${LOCAL_UID:-$(id -u appuser)}
export LOCAL_GID=${LOCAL_GID:-$(id -g appuser)}

# Check if the user needs to be created or modified
if [ "$LOCAL_UID" != "$(id -u appuser)" ] || [ "$LOCAL_GID" != "$(id -g appuser)" ]; then
  echo "Adjusting user UID/GID to match host user..."
  usermod --uid "$LOCAL_UID" appuser
  groupmod --gid "$LOCAL_GID" appuser
fi

# **FIX:** Ensure the entire /app directory is owned by the appuser
chown -R "$LOCAL_UID":"$LOCAL_GID" /app

# The DOCKER_HOST environment variable should be set to the path of the mounted socket.
# Ensure that the appuser can access the Docker socket
if [ -S "/var/run/docker.sock" ]; then
    chmod 666 /var/run/docker.sock
fi

# Execute the main application command as the appuser
exec gosu "$LOCAL_UID" "$@"
