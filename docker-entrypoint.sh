#!/bin/sh

# Set the UID and GID for the application process.
EXEC_UID=${LOCAL_UID:-1005}
EXEC_GID=${LOCAL_GID:-1005}

# Add the virtual environment's bin directory to the PATH immediately
export PATH="/app/venv/bin:$PATH"

# --- TEST ---
echo "--- DEBUG ---"
echo "Current PATH: $PATH"
echo "Looking for gunicorn in PATH..."
if command -v gunicorn >/dev/null 2>&1; then
    echo "gunicorn found at: $(command -v gunicorn)"
else
    echo "gunicorn NOT found."
fi
echo "--- END DEBUG ---"
# --- TEST END ---

# Log the UID and GID being used
echo "Running as UID: ${EXEC_UID}, GID: ${EXEC_GID}"

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

# The gosu command will execute the application with the specified user context.
exec gosu "$EXEC_UID:$EXEC_GID" "$@"
