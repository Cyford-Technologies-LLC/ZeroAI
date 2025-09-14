#!/bin/bash
# Setup cron job for queue processing

echo "Setting up queue processing cron job..."

# Create cron entry
CRON_JOB="* * * * * /usr/bin/php /app/scripts/process_queue.php >> /app/logs/queue.log 2>&1"

# Add to crontab
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

# Start cron service
service cron start

echo "Cron job setup complete. Queue will be processed every minute."
echo "Logs will be written to /app/logs/queue.log"