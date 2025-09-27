#!/usr/bin/env python3
"""
Video Support Functions
Complete implementation with all required imports
"""

import subprocess
import os
import logging

# Setup logging
logger = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')


def concat_videos(video_list, output_path):
    """Concatenate video files using ffmpeg"""
    try:
        list_file = "/tmp/concat_list.txt"
        with open(list_file, "w") as f:
            for v in video_list:
                f.write(f"file '{v}'\n")

        cmd = ["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", list_file, "-c", "copy", output_path]
        subprocess.run(cmd, check=True, capture_output=True)
        return output_path
    except Exception as e:
        logger.error(f"Video concatenation failed: {e}")
        return None


def convert_video_with_codec(video_path, audio_path, codec, quality):
    """Convert video using specified codec"""
    logger.info(f"Converting video with codec: {codec}, quality: {quality}")

    codec_configs = {
        'h264_high': {
            'video_codec': 'libx264', 'audio_codec': 'aac', 'preset': 'ultrafast',
            'crf': '23', 'profile': 'baseline', 'level': '3.0', 'extension': '.mp4'
        },
        'h264_medium': {
            'video_codec': 'libx264', 'audio_codec': 'aac', 'preset': 'medium',
            'crf': '23', 'profile': 'main', 'level': '4.0', 'extension': '.mp4'
        },
        'h264_fast': {
            'video_codec': 'libx264', 'audio_codec': 'aac', 'preset': 'ultrafast',
            'crf': '28', 'profile': 'baseline', 'level': '3.1', 'extension': '.mp4'
        },
        'h265_high': {
            'video_codec': 'libx265', 'audio_codec': 'aac', 'preset': 'slow',
            'crf': '20', 'extension': '.mp4'
        },
        'webm_high': {
            'video_codec': 'libvpx-vp9', 'audio_codec': 'libopus', 'crf': '20',
            'b:v': '2M', 'extension': '.webm'
        },
        'webm_fast': {
            'video_codec': 'libvpx', 'audio_codec': 'libvorbis', 'crf': '30',
            'b:v': '500k', 'b:a': '96k', 'cpu-used': '5', 'deadline': 'realtime',
            'extension': '.webm'
        }
    }

    config = codec_configs.get(codec, codec_configs['h264_fast'])
    output_path = video_path.replace('.avi', config['extension'])

    try:
        cmd = ['ffmpeg', '-i', video_path, '-i', audio_path]

        # Video settings
        cmd.extend(['-c:v', config['video_codec']])
        if 'preset' in config:
            cmd.extend(['-preset', config['preset']])
        if 'crf' in config:
            cmd.extend(['-crf', config['crf']])
        if 'profile' in config:
            cmd.extend(['-profile:v', config['profile']])
        if 'level' in config:
            cmd.extend(['-level', config['level']])
        if 'b:v' in config:
            cmd.extend(['-b:v', config['b:v']])

        # Audio settings
        cmd.extend(['-c:a', config['audio_codec']])
        if 'b:a' in config:
            cmd.extend(['-b:a', config['b:a']])

        # WebM specific settings
        if config['video_codec'] == 'libvpx':
            if 'cpu-used' in config:
                cmd.extend(['-cpu-used', config['cpu-used']])
            if 'deadline' in config:
                cmd.extend(['-deadline', config['deadline']])
            cmd.extend(['-auto-alt-ref', '0', '-lag-in-frames', '0'])

        # Universal settings
        cmd.extend(['-pix_fmt', 'yuv420p'])
        if config['extension'] == '.mp4':
            cmd.extend(['-movflags', '+faststart'])
        cmd.extend(['-shortest', '-y', output_path])

        result = subprocess.run(cmd, capture_output=True, text=True)

        if result.returncode == 0 and os.path.exists(output_path):
            logger.info(f"Video conversion successful: {output_path}")
            return output_path
        else:
            logger.error(f"Video conversion failed: {result.stderr}")
            return None

    except Exception as e:
        logger.error(f"Video conversion error: {e}")
        return None


def get_mimetype_for_codec(codec):
    """Get MIME type for codec"""
    mime_types = {
        'h264_high': 'video/mp4', 'h264_medium': 'video/mp4', 'h264_fast': 'video/mp4',
        'h265_high': 'video/mp4', 'webm_high': 'video/webm', 'webm_fast': 'video/webm'
    }
    return mime_types.get(codec, 'video/mp4')


# Example usage
if __name__ == "__main__":
    # Test the functions
    print("Video support functions loaded successfully")

    # Example: Get MIME type
    print(f"H264 High MIME type: {get_mimetype_for_codec('h264_high')}")
    print(f"WebM High MIME type: {get_mimetype_for_codec('webm_high')}")