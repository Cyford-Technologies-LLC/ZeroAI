
#!/usr/bin/env python3
"""
SadTalker Function with Required Imports Only
"""

import os
import subprocess
import logging
import glob
import shutil
from audio_processor import get_audio_duration, split_audio
from video_processor import concat_videos

# Setup logger
logger = logging.getLogger(__name__)



def generate_sadtalker_video(audio_path, video_path, prompt, codec, quality,
                             timeout=1200, enhancer=None, split_chunks=False,
                             chunk_length=10, source_image="/app/faces/2.jpg"):
    """Run SadTalker with optional chunking"""
    if not os.path.exists("/app/SadTalker"):
        logger.warning("SadTalker not found")
        return False

    try:
        result_dir = "/app/static/sadtalker_output"
        os.makedirs(result_dir, exist_ok=True)

        duration = get_audio_duration(audio_path)
        logger.info(f"Audio duration: {duration:.2f}s")

        # Auto-enable chunking for long audio
        if duration > 80 and not split_chunks:
            split_chunks = True
            logger.info("Auto-enabling chunking for long audio")

        if split_chunks:
            chunks = split_audio(audio_path, chunk_length)
            video_parts = []

            for idx, chunk_path in enumerate(chunks):
                logger.info(f"Processing chunk {idx + 1}/{len(chunks)}")
                chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
                os.makedirs(chunk_result_dir, exist_ok=True)

                cmd = [
                    "python", "/app/SadTalker/inference.py",
                    "--driven_audio", chunk_path,
                    "--source_image", source_image,
                    "--result_dir", chunk_result_dir,
                    "--still", "--preprocess", "crop"
                ]
                if enhancer:
                    cmd += ["--enhancer", enhancer]

                subprocess.run(cmd, timeout=timeout, check=True)

                # Find generated video
                video_candidates = glob.glob(os.path.join(chunk_result_dir, "**", "*.avi"), recursive=True)
                video_candidates += glob.glob(os.path.join(chunk_result_dir, "**", "*.mp4"), recursive=True)

                if video_candidates:
                    best_file = max(video_candidates, key=os.path.getmtime)
                    video_parts.append(best_file)
                else:
                    logger.error(f"No video found for chunk {idx}")
                    return False

            # Concatenate chunks
            if len(video_parts) > 1:
                concat_videos(video_parts, video_path)
            else:
                shutil.copy(video_parts[0], video_path)

            # Cleanup chunks
            for idx in range(len(chunks)):
                chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
                shutil.rmtree(chunk_result_dir, ignore_errors=True)
                chunk_file = f"{audio_path}_chunk{idx}.wav"
                if os.path.exists(chunk_file):
                    os.remove(chunk_file)
        else:
            # Single video generation
            cmd = [
                "python", "/app/SadTalker/inference.py",
                "--driven_audio", audio_path,
                "--source_image", source_image,
                "--result_dir", result_dir,
                "--still", "--preprocess", "crop"
            ]
            if enhancer:
                cmd += ["--enhancer", enhancer]

            subprocess.run(cmd, timeout=timeout, check=True)

            # Find generated video
            video_candidates = glob.glob(os.path.join(result_dir, "**", "*.avi"), recursive=True)
            video_candidates += glob.glob(os.path.join(result_dir, "**", "*.mp4"), recursive=True)

            if video_candidates:
                best_file = max(video_candidates, key=os.path.getmtime)
                shutil.copy(best_file, video_path)
            else:
                return False

        return True

    except Exception as e:
        logger.error(f"SadTalker generation failed: {e}")
        return False