#!/bin/bash
echo "Checking SadTalker checkpoint files..."
docker exec zeroai_avatar ls -la /app/SadTalker/checkpoints/
echo ""
echo "File sizes:"
docker exec zeroai_avatar find /app/SadTalker/checkpoints/ -type f -exec ls -lh {} \;