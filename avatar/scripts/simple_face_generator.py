#!/usr/bin/env python3
"""
Face Generation Functions with Required Imports
"""

import os
import logging
import cv2
import numpy as np
import mediapipe as mp

from audio_processor import get_audio_duration

# Setup logger
logger = logging.getLogger(__name__)


def generate_talking_face(image_path, audio_path, output_path, codec=None, quality=None):
    """Generate realistic talking face using MediaPipe"""
    try:
        logger.info("Starting face detection...")
        mp_face_detection = mp.solutions.face_detection

        # Load source image
        if os.path.exists(image_path):
            img = cv2.imread(image_path)
            logger.info(f"Loaded image: {image_path}")
        else:
            logger.info("Creating default face image...")
            img = create_default_face()

        with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))

            if results.detections:
                logger.info(f"Found {len(results.detections)} faces")
                detection = results.detections[0]
                h_img, w_img = img.shape[:2]
                rbb = detection.location_data.relative_bounding_box
                x = int(rbb.xmin * w_img)
                y = int(rbb.ymin * h_img)
                w = int(rbb.width * w_img)
                h = int(rbb.height * h_img)
                logger.info(f"Face bbox: x={x}, y={y}, w={w}, h={h}")

                create_animated_face(img, (x, y, w, h), audio_path, output_path)
            else:
                logger.info("No face detected, using basic avatar")
                create_basic_avatar(audio_path, output_path, "No face detected")

    except Exception as e:
        logger.error(f"Face generation error: {e}")
        create_basic_avatar(audio_path, output_path, f"Face animation failed: {str(e)}")


def create_default_face():
    """Create a realistic default face image"""
    img = np.ones((512, 512, 3), dtype=np.uint8) * 245

    # Draw face
    face_center = (256, 256)
    face_width, face_height = 180, 220

    # Face outline
    cv2.ellipse(img, face_center, (face_width // 2, face_height // 2), 0, 0, 360, (220, 200, 180), -1)

    # Eyes
    left_eye_center = (200, 220)
    right_eye_center = (312, 220)

    # Eye whites
    cv2.ellipse(img, left_eye_center, (25, 15), 0, 0, 360, (255, 255, 255), -1)
    cv2.ellipse(img, right_eye_center, (25, 15), 0, 0, 360, (255, 255, 255), -1)

    # Iris
    cv2.circle(img, left_eye_center, 12, (100, 150, 200), -1)
    cv2.circle(img, right_eye_center, 12, (100, 150, 200), -1)

    # Pupils
    cv2.circle(img, left_eye_center, 6, (20, 20, 20), -1)
    cv2.circle(img, right_eye_center, 6, (20, 20, 20), -1)

    # Eyebrows
    cv2.ellipse(img, (200, 195), (30, 8), 0, 0, 180, (120, 100, 80), -1)
    cv2.ellipse(img, (312, 195), (30, 8), 0, 0, 180, (120, 100, 80), -1)

    # Nose
    nose_points = np.array([[256, 240], [248, 270], [256, 280], [264, 270]], np.int32)
    cv2.fillPoly(img, [nose_points], (200, 180, 160))

    # Nostrils
    cv2.circle(img, (250, 275), 3, (180, 160, 140), -1)
    cv2.circle(img, (262, 275), 3, (180, 160, 140), -1)

    # Mouth
    cv2.ellipse(img, (256, 320), (35, 12), 0, 0, 180, (150, 100, 100), -1)

    return img


def create_animated_face(img, detection, audio_path, output_path):
    """Create animated face video"""
    logger.info("Creating animated face video...")
    fps = 30
    duration = get_audio_duration(audio_path)
    duration = max(0.1, float(duration))
    frames = int(round(fps * duration))

    height, width = img.shape[:2]

    # Try multiple codecs
    codec_choices = ['mp4v', 'MJPG', 'XVID']
    out = None

    for codec in codec_choices:
        try:
            fourcc = cv2.VideoWriter_fourcc(*codec)
            out = cv2.VideoWriter(output_path, fourcc, fps, (width, height))
            if out.isOpened():
                logger.info(f"Using codec: {codec}")
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as e:
            logger.error(f"Codec {codec} failed: {e}")
            continue

    if out is None:
        logger.error("All codecs failed, using basic avatar")
        create_basic_avatar(audio_path, output_path, "Codec failed")
        return

    try:
        # Parse detection
        if isinstance(detection, (tuple, list)) and len(detection) == 4:
            x, y, w, h = map(int, detection)
        else:
            bbox = detection.location_data.relative_bounding_box
            x = int(bbox.xmin * width)
            y = int(bbox.ymin * height)
            w = int(bbox.width * width)
            h = int(bbox.height * height)

        # Ensure valid bounds
        x = max(0, int(x))
        y = max(0, int(y))
        w = max(0, int(min(w, width - x)))
        h = max(0, int(min(h, height - y)))

        if w <= 0 or h <= 0:
            create_basic_avatar(audio_path, output_path, "Empty bbox")
            return

        # Render frames
        for i in range(frames):
            frame = img.copy()

            # Mouth animation
            mouth_intensity = abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1))

            face_region = frame[y:y + h, x:x + w]
            if face_region.size == 0:
                continue

            # Mouth geometry
            mouth_y_rel = int(h * 0.7)
            mouth_x_rel = int(w * 0.5)
            mouth_w = max(1, int(w * 0.2))
            mouth_h = max(1, int(5 + mouth_intensity * 20))

            # Draw mouth
            if mouth_y_rel < h and mouth_x_rel < w:
                cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel), (mouth_w, mouth_h),
                            0, 0, 180, (120, 80, 80), -1)

                if mouth_h > 10:
                    cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel - 2),
                                (max(1, mouth_w - 5), 3), 0, 0, 180, (240, 240, 240), -1)

            # Eye blinking
            if i % 120 < 8:
                eye_y_rel = int(h * 0.35)
                left_eye_x = int(w * 0.35)
                right_eye_x = int(w * 0.65)

                cv2.ellipse(face_region, (left_eye_x, eye_y_rel),
                            (max(1, int(w * 0.08)), 4), 0, 0, 180, (200, 180, 160), -1)
                cv2.ellipse(face_region, (right_eye_x, eye_y_rel),
                            (max(1, int(w * 0.08)), 4), 0, 0, 180, (200, 180, 160), -1)

            frame[y:y + h, x:x + w] = face_region
            out.write(frame)

        logger.info("Animated face video completed")

    finally:
        if out:
            out.release()


def create_basic_avatar(audio_path, video_path, text):
    """Fallback basic avatar"""
    logger.info("Creating basic avatar...")
    fps = 30
    duration = get_audio_duration(audio_path)
    frames = int(fps * duration)

    codecs = ['mp4v', 'MJPG', 'XVID']
    out = None

    for codec in codecs:
        try:
            fourcc = cv2.VideoWriter_fourcc(*codec)
            out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
            if out.isOpened():
                logger.info(f"Basic avatar using codec: {codec}")
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as e:
            logger.error(f"Basic codec {codec} failed: {e}")
            continue

    if out is None:
        logger.error("No working codec found!")
        return

    try:
        for i in range(frames):
            frame = np.zeros((480, 640, 3), dtype=np.uint8)
            frame.fill(30)

            # Draw basic animated face
            cv2.circle(frame, (320, 240), 100, (220, 200, 180), -1)  # Face
            cv2.circle(frame, (295, 215), 12, (80, 60, 40), -1)  # Left eye
            cv2.circle(frame, (345, 215), 12, (80, 60, 40), -1)  # Right eye
            cv2.circle(frame, (297, 213), 4, (255, 255, 255), -1)  # Left highlight
            cv2.circle(frame, (347, 213), 4, (255, 255, 255), -1)  # Right highlight

            # Nose
            cv2.ellipse(frame, (320, 245), (8, 12), 0, 0, 180, (200, 180, 160), -1)

            # Animate mouth
            mouth_open = 5 + int(15 * abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1)))
            cv2.ellipse(frame, (320, 275), (25, mouth_open), 0, 0, 180, (100, 80, 80), -1)

            # Eyebrows
            cv2.ellipse(frame, (295, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)
            cv2.ellipse(frame, (345, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)

            cv2.putText(frame, text[:30], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
            out.write(frame)

        logger.info("Basic avatar completed")
    finally:
        if out:
            out.release()