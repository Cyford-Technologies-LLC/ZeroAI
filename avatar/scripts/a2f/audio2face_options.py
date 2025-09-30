# audio2face_options.py - Complete A2F options handler with all parameters exposed

from typing import Dict, Any, Optional, List
import logging

logger = logging.getLogger(__name__)

class Audio2FaceOptions:
    """Complete Audio2Face options with all NVIDIA parameters exposed"""

    # All available A2F parameters based on NVIDIA documentation
    A2F_PARAMS = {
        # Core Animation Settings
        "animation": {
            "blend_shapes_weight": {"type": float, "default": 1.0, "range": [0.0, 2.0], "description": "Overall blendshape intensity"},
            "jaw_open_multiplier": {"type": float, "default": 1.0, "range": [0.0, 3.0], "description": "Jaw opening intensity"},
            "lip_sync_strength": {"type": float, "default": 1.0, "range": [0.0, 2.0], "description": "Lip sync accuracy vs smoothness"},
            "blink_frequency": {"type": float, "default": 0.3, "range": [0.0, 1.0], "description": "Automatic blinking frequency"},
            "blink_duration": {"type": float, "default": 0.15, "range": [0.1, 0.5], "description": "Duration of each blink"},
            "micro_expressions": {"type": bool, "default": True, "description": "Enable subtle facial movements"},
            "breathing_animation": {"type": bool, "default": True, "description": "Simulate breathing movements"},
        },

        # Emotion Parameters
        "emotion": {
            "emotion_type": {"type": str, "default": "neutral", "choices": ["neutral", "happy", "sad", "angry", "surprised", "fearful", "disgusted"], "description": "Base emotional state"},
            "emotion_intensity": {"type": float, "default": 0.5, "range": [0.0, 1.0], "description": "Strength of emotional expression"},
            "emotion_blend_time": {"type": float, "default": 0.5, "range": [0.1, 2.0], "description": "Transition time between emotions"},
            "enable_dynamic_emotions": {"type": bool, "default": False, "description": "Analyze audio for emotion detection"},
        },

        # Audio Processing
        "audio": {
            "audio_sample_rate": {"type": int, "default": 22050, "choices": [16000, 22050, 44100, 48000], "description": "Audio sampling rate"},
            "audio_amplitude_multiplier": {"type": float, "default": 1.0, "range": [0.1, 3.0], "description": "Audio volume adjustment"},
            "pre_process_audio": {"type": bool, "default": True, "description": "Apply noise reduction and normalization"},
            "voice_conversion": {"type": str, "default": "none", "choices": ["none", "male_to_female", "female_to_male", "child", "elderly"], "description": "Voice transformation"},
            "smoothing_window": {"type": int, "default": 3, "range": [1, 10], "description": "Audio analysis smoothing frames"},
        },

        # Character & Rendering
        "character": {
            "character_path": {"type": str, "default": None, "description": "USD character file path or preset name"},
            "character_preset": {"type": str, "default": "james", "choices": ["james", "mark", "allison", "claire", "custom"], "description": "Built-in character preset"},
            "skin_weight_smoothing": {"type": float, "default": 0.5, "range": [0.0, 1.0], "description": "Skin deformation smoothness"},
            "subdivision_level": {"type": int, "default": 1, "range": [0, 3], "description": "Mesh subdivision for quality"},
            "enable_wrinkles": {"type": bool, "default": True, "description": "Dynamic wrinkle maps"},
            "eye_look_at": {"type": str, "default": "camera", "choices": ["camera", "forward", "custom"], "description": "Eye gaze direction"},
            "custom_gaze_target": {"type": list, "default": [0, 0, 100], "description": "Custom gaze target [x, y, z]"},
        },

        # Post Processing
        "post_processing": {
            "apply_post_processing": {"type": bool, "default": True, "description": "Enable post-processing pipeline"},
            "temporal_filter": {"type": bool, "default": True, "description": "Reduce temporal jitter"},
            "spatial_filter": {"type": bool, "default": True, "description": "Smooth spatial movements"},
            "filter_strength": {"type": float, "default": 0.5, "range": [0.0, 1.0], "description": "Overall filter intensity"},
            "motion_blur": {"type": bool, "default": False, "description": "Add motion blur to fast movements"},
            "depth_of_field": {"type": bool, "default": False, "description": "Enable DOF effect"},
        },

        # Performance & Output
        "performance": {
            "gpu_acceleration": {"type": bool, "default": True, "description": "Use GPU for processing"},
            "batch_size": {"type": int, "default": 1, "range": [1, 8], "description": "Batch processing size"},
            "cache_size": {"type": int, "default": 100, "range": [10, 1000], "description": "Frame cache size in MB"},
            "multi_threading": {"type": bool, "default": True, "description": "Use multiple CPU threads"},
            "preview_quality": {"type": str, "default": "medium", "choices": ["low", "medium", "high", "ultra"], "description": "Preview rendering quality"},
        },

        # Output Settings
        "output": {
            "output_format": {"type": str, "default": "mp4", "choices": ["mp4", "mov", "avi", "webm", "frames"], "description": "Output file format"},
            "resolution": {"type": str, "default": "1920x1080", "choices": ["640x480", "1280x720", "1920x1080", "2560x1440", "3840x2160"], "description": "Output resolution"},
            "fps": {"type": int, "default": 30, "choices": [24, 25, 30, 60, 120], "description": "Frames per second"},
            "codec": {"type": str, "default": "h264", "choices": ["h264", "h265", "vp9", "av1", "prores"], "description": "Video codec"},
            "bitrate": {"type": str, "default": "10M", "description": "Video bitrate (e.g., 10M, 20M)"},
            "include_alpha": {"type": bool, "default": False, "description": "Include alpha channel"},
        },

        # Advanced Features
        "advanced": {
            "enable_arkit_blendshapes": {"type": bool, "default": True, "description": "Use ARKit-compatible blendshapes"},
            "retargeting_config": {"type": str, "default": "default", "description": "Custom retargeting configuration"},
            "solver_iterations": {"type": int, "default": 5, "range": [1, 20], "description": "IK solver iterations"},
            "constraint_stiffness": {"type": float, "default": 0.8, "range": [0.0, 1.0], "description": "Physics constraint stiffness"},
            "enable_secondary_motion": {"type": bool, "default": True, "description": "Hair/clothing dynamics"},
            "muscle_simulation": {"type": bool, "default": False, "description": "Advanced muscle system"},
        }
    }

    @classmethod
    def get_default_options(cls) -> Dict[str, Any]:
        """Get all default options"""
        defaults = {}
        for category, params in cls.A2F_PARAMS.items():
            defaults[category] = {}
            for param, config in params.items():
                defaults[category][param] = config["default"]
        return defaults

    @classmethod
    def validate_options(cls, options: Dict[str, Any]) -> tuple[bool, str, Dict[str, Any]]:
        """
        Validate and sanitize Audio2Face options

        Returns:
            tuple: (is_valid, error_message, sanitized_options)
        """
        sanitized = cls.get_default_options()
        errors = []

        for category, params in options.items():
            if category not in cls.A2F_PARAMS:
                errors.append(f"Unknown category: {category}")
                continue

            if not isinstance(params, dict):
                errors.append(f"Category {category} must be a dictionary")
                continue

            for param, value in params.items():
                if param not in cls.A2F_PARAMS[category]:
                    errors.append(f"Unknown parameter: {category}.{param}")
                    continue

                config = cls.A2F_PARAMS[category][param]
                param_type = config["type"]

                # Type validation
                if not isinstance(value, param_type):
                    try:
                        value = param_type(value)
                    except:
                        errors.append(f"{category}.{param} must be {param_type.__name__}")
                        continue

                # Range validation
                if "range" in config:
                    min_val, max_val = config["range"]
                    if value < min_val or value > max_val:
                        errors.append(f"{category}.{param} must be between {min_val} and {max_val}")
                        continue

                # Choice validation
                if "choices" in config and value not in config["choices"]:
                    errors.append(f"{category}.{param} must be one of {config['choices']}")
                    continue

                sanitized[category][param] = value

        if errors:
            return False, "; ".join(errors), sanitized

        return True, "", sanitized

    @classmethod
    def merge_with_defaults(cls, user_options: Dict[str, Any]) -> Dict[str, Any]:
        """Merge user options with defaults"""
        merged = cls.get_default_options()

        for category, params in user_options.items():
            if category in merged and isinstance(params, dict):
                merged[category].update(params)

        return merged

    @classmethod
    def get_options_documentation(cls) -> Dict[str, Any]:
        """Get complete documentation of all options"""
        docs = {}
        for category, params in cls.A2F_PARAMS.items():
            docs[category] = {}
            for param, config in params.items():
                docs[category][param] = {
                    "type": config["type"].__name__,
                    "default": config["default"],
                    "description": config.get("description", ""),
                }
                if "range" in config:
                    docs[category][param]["range"] = config["range"]
                if "choices" in config:
                    docs[category][param]["choices"] = config["choices"]
        return docs

    @classmethod
    def create_preset(cls, preset_name: str) -> Dict[str, Any]:
        """Create option presets for common use cases"""
        presets = {
            "high_quality": {
                "animation": {
                    "lip_sync_strength": 1.2,
                    "micro_expressions": True,
                    "breathing_animation": True
                },
                "character": {
                    "subdivision_level": 2,
                    "enable_wrinkles": True
                },
                "post_processing": {
                    "apply_post_processing": True,
                    "temporal_filter": True,
                    "spatial_filter": True,
                    "filter_strength": 0.7
                },
                "output": {
                    "resolution": "1920x1080",
                    "fps": 60,
                    "codec": "h265",
                    "bitrate": "20M"
                }
            },
            "fast_preview": {
                "animation": {
                    "lip_sync_strength": 0.8,
                    "micro_expressions": False,
                    "breathing_animation": False
                },
                "character": {
                    "subdivision_level": 0,
                    "enable_wrinkles": False
                },
                "post_processing": {
                    "apply_post_processing": False
                },
                "output": {
                    "resolution": "1280x720",
                    "fps": 30,
                    "codec": "h264",
                    "bitrate": "5M"
                }
            },
            "streaming": {
                "animation": {
                    "lip_sync_strength": 1.0,
                    "micro_expressions": True,
                    "blink_frequency": 0.4
                },
                "audio": {
                    "smoothing_window": 2,
                    "pre_process_audio": True
                },
                "performance": {
                    "gpu_acceleration": True,
                    "batch_size": 1,
                    "cache_size": 50
                },
                "output": {
                    "resolution": "1280x720",
                    "fps": 30,
                    "codec": "h264",
                    "bitrate": "3M"
                }
            },
            "emotional": {
                "emotion": {
                    "enable_dynamic_emotions": True,
                    "emotion_intensity": 0.8,
                    "emotion_blend_time": 0.3
                },
                "animation": {
                    "micro_expressions": True,
                    "breathing_animation": True
                }
            }
        }

        if preset_name in presets:
            return cls.merge_with_defaults(presets[preset_name])

        return cls.get_default_options()

# Integration helper functions
def prepare_audio2face_request(user_data: Dict[str, Any]) -> Dict[str, Any]:
    """
    Prepare a complete Audio2Face request with all options

    Args:
        user_data: User-provided options

    Returns:
        Dict with validated and complete options
    """
    # Extract A2F specific options
    a2f_options = {}

    # Check for nested a2f_options
    if "a2f_options" in user_data:
        a2f_options = user_data["a2f_options"]

    # Also check for flat options (backward compatibility)
    for category in Audio2FaceOptions.A2F_PARAMS:
        if category in user_data:
            a2f_options[category] = user_data[category]

    # Apply preset if requested
    if "preset" in user_data:
        preset_options = Audio2FaceOptions.create_preset(user_data["preset"])
        a2f_options = Audio2FaceOptions.merge_with_defaults(a2f_options)
        for category, params in preset_options.items():
            if category not in a2f_options:
                a2f_options[category] = {}
            a2f_options[category].update(params)

    # Validate and merge with defaults
    is_valid, error_msg, sanitized = Audio2FaceOptions.validate_options(a2f_options)

    if not is_valid:
        logger.warning(f"Option validation errors: {error_msg}")

    return {
        "prompt": user_data.get("prompt", ""),
        "tts_engine": user_data.get("tts_engine", "espeak"),
        "tts_options": user_data.get("tts_options", {}),
        "force_mock": user_data.get("force_mock", False),
        "a2f_server_url": user_data.get("a2f_server_url", "http://localhost:8011"),
        "a2f_options": sanitized,
        "validation_errors": error_msg if not is_valid else None
    }