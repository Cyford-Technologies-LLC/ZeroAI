#!/usr/bin/env python3
import requests
import json

print("=== Testing Current Avatar API ===")

try:
    # Test health endpoint
    print("1. Testing health...")
    health_response = requests.get("http://localhost:7860/health", timeout=5)
    print(f"Health Status: {health_response.status_code}")
    print(f"Health Response: {health_response.text}")
    
    # Test generate endpoint
    print("\n2. Testing generate...")
    generate_data = {"prompt": "Hello world test"}
    generate_response = requests.post(
        "http://localhost:7860/generate",
        json=generate_data,
        timeout=30
    )
    print(f"Generate Status: {generate_response.status_code}")
    print(f"Generate Response: {generate_response.text[:500]}")
    
    if generate_response.status_code != 200:
        print(f"ERROR: {generate_response.status_code}")
        print(f"Response: {generate_response.text}")
    
except Exception as e:
    print(f"Connection error: {e}")