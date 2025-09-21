#!/usr/bin/env python3
"""
Universal SadTalker inference wrapper
Automatically detects GPU/CPU and adjusts parameters accordingly
"""

import os
import sys
import argparse
import torch
import subprocess

def main():
    parser = argparse.ArgumentParser(description='Universal SadTalker inference')
    parser.add_argument('--driven_audio', required=True, help='Path to audio file')
    parser.add_argument('--source_image', required=True, help='Path to source image')
    parser.add_argument('--result_dir', required=True, help='Output directory')
    parser.add_argument('--still', action='store_true', help='Still mode')
    parser.add_argument('--preprocess', default='crop', help='Preprocessing method')
    parser.add_argument('--enhancer', default='gfpgan', help='Face enhancer')
    
    args = parser.parse_args()
    
    # Detect device capabilities
    device = "cuda" if torch.cuda.is_available() else "cpu"
    print(f"Device detected: {device}")
    
    # Build command for original inference.py
    sadtalker_path = '/app/SadTalker'
    inference_script = os.path.join(sadtalker_path, 'inference.py')
    
    if not os.path.exists(inference_script):
        print(f"ERROR: SadTalker inference.py not found at {inference_script}")
        sys.exit(1)
    
    cmd = [
        'python', inference_script,
        '--driven_audio', args.driven_audio,
        '--source_image', args.source_image,
        '--result_dir', args.result_dir
    ]
    
    # Add device-specific parameters
    if device == "cpu":
        print("Using CPU mode - adding CPU-specific parameters")
        cmd.extend(['--cpu'])
    else:
        print("Using GPU mode - adding GPU-specific parameters")
    
    # Add common parameters
    if args.still:
        cmd.append('--still')
    
    cmd.extend(['--preprocess', args.preprocess])
    
    if args.enhancer and device == "cuda":  # Only use enhancer on GPU
        cmd.extend(['--enhancer', args.enhancer])
    
    print(f"Executing: {' '.join(cmd)}")
    
    # Execute the original inference script
    try:
        result = subprocess.run(cmd, check=True, capture_output=True, text=True)
        print("SadTalker execution successful")
        print(f"Output: {result.stdout}")
        if result.stderr:
            print(f"Warnings: {result.stderr}")
        return 0
    except subprocess.CalledProcessError as e:
        print(f"SadTalker execution failed: {e}")
        print(f"Return code: {e.returncode}")
        print(f"Stdout: {e.stdout}")
        print(f"Stderr: {e.stderr}")
        return e.returncode
    except Exception as e:
        print(f"Unexpected error: {e}")
        return 1

if __name__ == '__main__':
    sys.exit(main())