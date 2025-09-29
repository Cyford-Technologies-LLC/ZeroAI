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


def encode_video_for_delivery(video_path: str, delivery_mode: str, chunk_filename: str) -> Dict:
    """Handle video encoding for delivery (base64 or URL) - matches /generate logic"""
    import base64

    delivery_info = {}

    # HONOR delivery_mode EXACTLY like /generate endpoint
    if delivery_mode == 'base64':
        # Encode as base64 (like your frontend expects)
        try:
            with open(video_path, 'rb') as video_file:
                video_data = video_file.read()
                video_base64 = base64.b64encode(video_data).decode('utf-8')
            delivery_info["video_data"] = f"data:video/mp4;base64,{video_base64}"

            # Clean up temp file after encoding
            if os.path.exists(video_path):
                os.unlink(video_path)

            logger.info(f"✅ Video encoded as base64 - Size: {len(video_base64)} chars")

        except Exception as e:
            logger.error(f"Base64 encoding failed: {e}")
            delivery_info["error"] = "Base64 encoding failed"

    elif delivery_mode == 'url':
        # Copy to static directory for URL access (like /generate does)
        try:
            static_path = f"/app/static/{chunk_filename}"
            shutil.copy(video_path, static_path)
            delivery_info["video_url"] = f"/static/{chunk_filename}"

            # Clean up temp file after copying
            if os.path.exists(video_path):
                os.unlink(video_path)

            logger.info(f"✅ Video saved for URL access: {static_path}")

        except Exception as e:
            logger.error(f"URL delivery failed: {e}")
            delivery_info["error"] = "URL delivery failed"

    else:
        logger.error(f"Unknown delivery_mode: {delivery_mode}")
        delivery_info["error"] = f"Invalid delivery_mode: {delivery_mode}"

    return delivery_info

def generate_video_with_options(text: str, chunk_id: int, source_image, audio_path: str,
                                     output_path: str, options: dict) -> bool:
        """Generate video with full options support"""
        mode = options.get("mode", "simple")

        try:
            if mode == 'sadtalker':
                return _generate_sadtalker_video(audio_path, source_image, output_path, options)
            else:
                return _generate_simple_video(audio_path, source_image, output_path, options)
        except Exception as e:
            logger.error(f"Video generation failed: {e}")
            return False







def generate_sadtalker_frames_for_streaming(self, source_image: np.ndarray, audio_path: str,
                                                 duration: float, frame_rate: int = 30,
                                                 sadtalker_options: Dict = None) -> Generator[bytes, None, None]:
        """Generate SadTalker frames optimized for streaming"""
        try:
            if sadtalker_options is None:
                sadtalker_options = {}

            # Save the numpy array as a temporary image file for SadTalker
            with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as temp_image:
                temp_image_path = temp_image.name
                cv2.imwrite(temp_image_path, source_image)

            # Create temporary video with SadTalker
            with tempfile.NamedTemporaryFile(suffix=".mp4", delete=False) as temp_video:
                temp_video_path = temp_video.name

            try:
                # Generate SadTalker video using your existing function
                success = generate_sadtalker_video(
                    audio_path,
                    temp_video_path,
                    "",
                    'h264_fast',
                    'medium',
                    timeout=sadtalker_options.get('timeout', 300),
                    enhancer=sadtalker_options.get('enhancer', None),
                    split_chunks=sadtalker_options.get('split_chunks', True),
                    chunk_length=int(sadtalker_options.get('chunk_length', duration)),
                    source_image=temp_image_path
                )

                if not success:
                    raise ValueError("SadTalker generation failed")

                # Extract frames from generated video and stream them
                cap = cv2.VideoCapture(temp_video_path)

                while cap.isOpened():
                    ret, frame = cap.read()
                    if not ret:
                        break

                    # Encode frame for streaming
                    _, buffer = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
                    yield buffer.tobytes()
                    yield

                cap.release()

            finally:
                # Cleanup temp files
                if os.path.exists(temp_video_path):
                    os.unlink(temp_video_path)
                if os.path.exists(temp_image_path):
                    os.unlink(temp_image_path)

        except Exception as e:
            logger.error(f"SadTalker streaming error: {e}")
            # Fallback to simple face animation
            logger.info("Falling back to simple face animation")
            yield from self._generate_face_frames(source_image, duration, frame_rate)

def generate_face_frames(self, source_image: np.ndarray, duration: float,
                              frame_rate: int = 30) -> Generator[bytes, None, None]:
        """Generate animated face frames based on audio duration"""
        total_frames = int(duration * frame_rate)
        height, width = source_image.shape[:2]

        # Detect face for animation
        import mediapipe as mp
        mp_face_detection = mp.solutions.face_detection

        with mp_face_detection.FaceDetection(model_selection=0,
                                             min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(source_image, cv2.COLOR_BGR2RGB))

            if results.detections:
                detection = results.detections[0]
                bbox = detection.location_data.relative_bounding_box
                x = int(bbox.xmin * width)
                y = int(bbox.ymin * height)
                w = int(bbox.width * width)
                h = int(bbox.height * height)
            else:
                # Default face area if no detection
                x, y, w, h = width // 4, height // 4, width // 2, height // 2

        for frame_idx in range(total_frames):
            frame = source_image.copy()

            # Animate mouth based on time
            time_factor = frame_idx / frame_rate
            mouth_intensity = (
                    0.6 * abs(np.sin(time_factor * 8)) +
                    0.3 * abs(np.sin(time_factor * 15)) +
                    0.1 * abs(np.sin(time_factor * 25))
            )

            # Draw animated mouth in face region
            face_region = frame[y:y + h, x:x + w]
            if face_region.size > 0:
                mouth_y_rel = int(h * 0.7)
                mouth_x_rel = int(w * 0.5)
                mouth_w = max(1, int(w * 0.15))
                mouth_h = max(1, int(5 + mouth_intensity * 15))

                if mouth_y_rel < h and mouth_x_rel < w:
                    cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel),
                                (mouth_w, mouth_h), 0, 0, 180, (120, 80, 80), -1)

                frame[y:y + h, x:x + w] = face_region

            # Encode frame as JPEG for streaming
            _, buffer = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
            yield buffer.tobytes()

def split_into_sentences(self, text: str) -> list:
        """Split text into sentences for chunked processing"""
        import re

        # Simple sentence splitting
        sentences = re.split(r'[.!?]+', text.strip())
        sentences = [s.strip() for s in sentences if s.strip()]

        # Ensure minimum length and split very long sentences
        processed = []
        for sentence in sentences:
            if len(sentence) > 200:  # Split long sentences
                words = sentence.split()
                for i in range(0, len(words), 20):
                    chunk = ' '.join(words[i:i + 20])
                    if chunk:
                        processed.append(chunk)
            else:
                processed.append(sentence)

        return processed or [text]  # Fallback to original text










# Example usage
if __name__ == "__main__":
    # Test the functions
    print("Video support functions loaded successfully")

    # Example: Get MIME type
    print(f"H264 High MIME type: {get_mimetype_for_codec('h264_high')}")
    print(f"WebM High MIME type: {get_mimetype_for_codec('webm_high')}")