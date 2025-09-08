#!/bin/bash
# Kill everything on port 333 and start PHP server
fuser -k 333/tcp
pkill -f "python.*333"
pkill -f "apache.*333"
sleep 2
cd /app/www
php -S 0.0.0.0:333