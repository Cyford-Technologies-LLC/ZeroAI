#!/bin/bash
echo "=== Testing Avatar API ==="

echo "1. Testing health endpoint..."
curl -s http://zeroai_avatar:7860/health | jq .

echo -e "\n2. Testing generate endpoint..."
curl -X POST http://zeroai_avatar:7860/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Hello world"}' \
  -v

echo -e "\n3. Checking avatar logs..."
docker logs zeroai_avatar --tail 20