#!/usr/bin/env python3
import sys
import traceback

print("=== Avatar Debug Script ===")

try:
    print("Testing imports...")
    
    print("1. Testing Flask...")
    from flask import Flask
    print("   ✓ Flask OK")
    
    print("2. Testing OpenCV...")
    import cv2
    print(f"   ✓ OpenCV OK - version: {cv2.__version__}")
    
    print("3. Testing NumPy...")
    import numpy as np
    print(f"   ✓ NumPy OK - version: {np.__version__}")
    
    print("4. Testing MediaPipe...")
    import mediapipe as mp
    print(f"   ✓ MediaPipe OK - version: {mp.__version__}")
    
    print("5. Testing TTS...")
    from TTS.api import TTS
    print("   ✓ TTS import OK")
    
    print("6. Testing TTS initialization...")
    tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph", gpu=False)
    print("   ✓ TTS initialization OK")
    
    print("7. Testing video codec...")
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    test_writer = cv2.VideoWriter('/tmp/test.mp4', fourcc, 30, (640, 480))
    if test_writer.isOpened():
        print("   ✓ Video codec OK")
        test_writer.release()
    else:
        print("   ✗ Video codec FAILED")
    
    print("\n=== All tests passed! ===")
    
except Exception as e:
    print(f"\n=== ERROR: {str(e)} ===")
    print(traceback.format_exc())
    sys.exit(1)