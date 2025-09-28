#!/usr/bin/env python3
# audio2face_quickstart.py - Quick test of Audio2Face integration

import requests
import json
import time
import os


def test_audio2face_integration(base_url="http://localhost:7860"):
    """
    Test your Audio2Face integration step by step
    """
    print("=== AUDIO2FACE INTEGRATION TEST ===\n")

    # Step 1: Check overall health
    print("1. Checking server health...")
    try:
        response = requests.get(f"{base_url}/health")
        if response.status_code == 200:
            health = response.json()
            print(f"   ✅ Server is running")
            print(f"   📦 Available modes: {health.get('modes', [])}")
            print(f"   🔊 TTS ready: {health.get('tts_ready', False)}")

            a2f_info = health.get('audio2face', {})
            if a2f_info:
                print(f"   🎭 Audio2Face available: {a2f_info.get('available', False)}")
                print(f"   🔗 Server reachable: {a2f_info.get('server_reachable', False)}")
        else:
            print(f"   ❌ Server health check failed: {response.status_code}")
            return False
    except Exception as e:
        print(f"   ❌ Cannot connect to server: {e}")
        return False

    print()

    # Step 2: Check Audio2Face specific status
    print("2. Checking Audio2Face status...")
    try:
        response = requests.get(f"{base_url}/audio2face/status")
        if response.status_code == 200:
            status = response.json()
            print(f"   📊 Recommended mode: {status.get('recommended_mode', 'unknown')}")

            real_a2f = status.get('real_audio2face', {})
            mock_a2f = status.get('mock_audio2face', {})

            print(f"   🔴 Real Audio2Face available: {real_a2f.get('available', False)}")
            print(f"   🔵 Mock Audio2Face available: {mock_a2f.get('available', False)}")

            if real_a2f.get('available') and 'status' in real_a2f:
                real_status = real_a2f['status']
                print(f"      - Server reachable: {real_status.get('server_reachable', False)}")
                print(f"      - Characters available: {len(real_status.get('characters_available', []))}")
                print(f"      - Requirements met: {real_status.get('requirements_met', False)}")
        else:
            print(f"   ❌ Audio2Face status check failed: {response.status_code}")
    except Exception as e:
        print(f"   ❌ Audio2Face status error: {e}")

    print()

    # Step 3: List characters
    print("3. Listing available characters...")
    try:
        response = requests.get(f"{base_url}/audio2face/characters")
        if response.status_code == 200:
            chars = response.json()
            print(f"   🎭 Mode: {chars.get('mode', 'unknown')}")
            print(f"   📁 Source: {chars.get('source', 'unknown')}")
            print(f"   📝 Characters ({chars.get('count', 0)}):")

            for char in chars.get('characters', []):
                print(f"      - {char}")

            if chars.get('current_character'):
                print(f"   ⭐ Current: {chars['current_character']}")
        else:
            print(f"   ❌ Character listing failed: {response.status_code}")
    except Exception as e:
        print(f"   ❌ Character listing error: {e}")

    print()

    # Step 4: Test generation
    print("4. Testing avatar generation...")

    test_cases = [
        {
            "name": "Simple Test",
            "data": {
                "prompt": "Hello! This is a test of the Audio2Face integration.",
                "tts_engine": "espeak",
                "tts_options": {"voice": "en+f3", "rate": "175"}
            }
        },
        {
            "name": "Force Mock Test",
            "data": {
                "prompt": "This test forces mock mode to verify fallback works.",
                "force_mock": True,
                "tts_engine": "espeak"
            }
        }
    ]

    for test_case in test_cases:
        print(f"\n   Testing: {test_case['name']}")
        try:
            print(f"   📤 Sending request...")
            response = requests.post(
                f"{base_url}/generate/audio2face",
                json=test_case['data'],
                stream=True,
                timeout=60
            )

            if response.status_code == 200:
                # Save the video
                filename = f"test_{test_case['name'].lower().replace(' ', '_')}_{int(time.time())}.mp4"
                filepath = f"/tmp/{filename}"

                with open(filepath, 'wb') as f:
                    for chunk in response.iter_content(chunk_size=8192):
                        f.write(chunk)

                file_size = os.path.getsize(filepath)

                print(f"   ✅ Generation successful!")
                print(f"      - File: {filepath}")
                print(f"      - Size: {file_size:,} bytes")

                # Check headers for mode info
                mode = response.headers.get('X-Audio2Face-Mode', 'unknown')
                reason = response.headers.get('X-Audio2Face-Reason', 'unknown')
                character = response.headers.get('X-Character-Used', 'unknown')

                print(f"      - Mode used: {mode}")
                print(f"      - Reason: {reason}")
                print(f"      - Character: {character}")

            else:
                print(f"   ❌ Generation failed: {response.status_code}")
                try:
                    error_info = response.json()
                    print(f"      Error: {error_info.get('error', 'Unknown error')}")
                except:
                    print(f"      Raw response: {response.text[:200]}")

        except Exception as e:
            print(f"   ❌ Test error: {e}")

    print()

    # Step 5: Performance test (optional)
    print("5. Quick performance test...")
    try:
        start_time = time.time()
        response = requests.post(
            f"{base_url}/generate/audio2face",
            json={
                "prompt": "Performance test.",
                "force_mock": True,  # Use mock for speed
                "tts_engine": "espeak"
            },
            timeout=30
        )
        end_time = time.time()

        if response.status_code == 200:
            duration = end_time - start_time
            print(f"   ⚡ Mock generation time: {duration:.2f} seconds")
        else:
            print(f"   ❌ Performance test failed: {response.status_code}")

    except Exception as e:
        print(f"   ❌ Performance test error: {e}")

    print("\n=== TEST COMPLETE ===")
    print("\nNext steps:")
    print("1. If mock mode works: Audio2Face integration is properly set up!")
    print("2. If real mode fails: Install and configure NVIDIA Audio2Face")
    print("3. Check /app/static/ for generated test videos")
    print("4. Use force_mock=false to test real Audio2Face when ready")


def quick_curl_examples():
    """Print curl examples for manual testing"""
    print("\n=== CURL TEST EXAMPLES ===\n")

    examples = [
        {
            "name": "Check Status",
            "curl": """curl -X GET http://localhost:7860/audio2face/status | jq '.'"""
        },
        {
            "name": "List Characters",
            "curl": """curl -X GET http://localhost:7860/audio2face/characters | jq '.'"""
        },
        {
            "name": "Simple Generation",
            "curl": """curl -X POST http://localhost:7860/generate/audio2face \\
  -H "Content-Type: application/json" \\
  -d '{
    "prompt": "Hello, this is a test!",
    "tts_engine": "espeak"
  }' \\
  --output test_audio2face.mp4"""
        },
        {
            "name": "Force Mock Mode",
            "curl": """curl -X POST http://localhost:7860/generate/audio2face \\
  -H "Content-Type: application/json" \\
  -d '{
    "prompt": "Testing mock mode",
    "force_mock": true,
    "tts_engine": "espeak"
  }' \\
  --output test_mock.mp4"""
        },
        {
            "name": "With Character and Voice",
            "curl": """curl -X POST http://localhost:7860/generate/audio2face \\
  -H "Content-Type: application/json" \\
  -d '{
    "prompt": "Advanced test with specific character and voice settings.",
    "character_path": "MockCharacter_Female_01",
    "tts_engine": "espeak",
    "tts_options": {
      "voice": "en+f3",
      "rate": "175",
      "pitch": "50"
    }
  }' \\
  --output test_advanced.mp4"""
        }
    ]

    for example in examples:
        print(f"{example['name']}:")
        print(f"  {example['curl']}")
        print()


if __name__ == "__main__":
    import sys

    if len(sys.argv) > 1 and sys.argv[1] == "--curl":
        quick_curl_examples()
    else:
        test_audio2face_integration()