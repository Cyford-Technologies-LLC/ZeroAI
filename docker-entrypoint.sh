#!/bin/sh
export LOCAL_UID=${LOCAL_UID:-1000}
export LOCAL_GID=${LOCAL_GID:-1000}

useradd --create-home --shell /bin/bash -u $LOCAL_UID -g $LOCAL_GID appuser


# Source the user's profile to ensure PATH is correctly set
. "$HOME/.profile"

# Change ownership of the config directory to the appuser
chown -R appuser:appuser /app/
#chown -R appuser:appuser /app/config

# The DOCKER_HOST environment variable should be set to the path of the mounted socket.
# Ensure that the appuser can access the Docker socket
if [ -S "/var/run/docker.sock" ]; then
    chmod 666 /var/run/docker.sock
fi


# Execute the original command
exec "$@"
