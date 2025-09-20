#!/usr/bin/env python3
import requests
import json

print("Testing avatar API error...")

try:
    response = requests.post(
        "http://zeroai_avatar:7860/generate",
        json={"prompt": "Hello"},
        timeout=30
    )
    
    print(f"Status: {response.status_code}")
    print(f"Headers: {dict(response.headers)}")
    print(f"Response: {response.text}")
    
except Exception as e:
    print(f"Error: {e}")