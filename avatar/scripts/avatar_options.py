# avatar_options.py

# All allowed options and their defaults
ALLOWED_OPTIONS = {
    "generate_avatar": {
        # Mode / generation
        "mode": "simple",  # simple | sadtalker
        "stream_mode": "complete",  # complete | chunked | realtime
        "codec": "h264_high",  # codec presets
        "quality": "high",  # high | medium | fast

        # TTS / speech
        "prompt": "Hello, this is a test.",
        "tts_engine": "espeak",
        "voice": "en_female",
        "language": "en-US",
        "rate": 1.0,
        "pitch": 0,
        "emotion": "neutral",
        "sample_rate": 16000,
        "format": "wav",

        # Source image / avatar
        "image": "/app/faces/2.jpg",  # URL, local path, or base64
        "still": True,
        "preprocess": "crop",  # crop | none | resize
        "resolution": "256",

        # SadTalker-specific video options
        "timeout": 1200,
        "enhancer": None,
        "split_chunks": False,
        "chunk_length": 10,
        "fps": 15,
    },

    # NEW: Streaming endpoint options
    "stream_avatar": {
        # Core streaming settings
        "streaming_mode": "chunked",  # chunked | realtime
        "prompt": "Hello, this is a streaming test.",
        "image": "/app/faces/2.jpg",

        # TTS options (streamlined for streaming)
        "tts_engine": "espeak",
        "tts_options": {},  # Dict for engine-specific options

        # Video/streaming settings
        "codec": "h264_fast",  # Optimized for streaming
        "quality": "medium",  # medium | fast | high
        "frame_rate": 25,  # 15 | 20 | 24 | 25 | 30

        # Chunked mode settings
        "chunk_duration": 3.0,  # Seconds per chunk
        "max_duration": 300.0,  # Maximum total duration (5 min)

        # Realtime mode settings
        "buffer_size": 5,  # Frames to buffer ahead
        "low_latency": False,  # Optimize for minimal delay

        # Advanced streaming options
        "adaptive_quality": True,  # Adjust quality based on performance
        "enable_websocket": False,  # Enable WebSocket mode
        "max_concurrent": 3,  # Max concurrent streams per client
    },

    # Optional: endpoints for debugging / health
    "debug": {
        "status": True,
        "logs": True,
        "reload": True,
        "health": True,
        "streaming": True,  # NEW: streaming debug info
        "metrics": True,  # NEW: streaming metrics
    }
}

# Validation rules for specific options
VALIDATION_RULES = {
    "streaming_mode": ["chunked", "realtime"],
    "codec": ["h264_high", "h264_medium", "h264_fast", "h265_high", "webm_high", "webm_fast"],
    "quality": ["high", "medium", "fast"],
    "tts_engine": ["espeak", "festival", "pico", "flite"],
    "frame_rate": [15, 20, 24, 25, 30, 60],
    "mode": ["simple", "sadtalker"],
    "preprocess": ["crop", "none", "resize"],
}

# Optimization presets for different streaming scenarios
STREAMING_PRESETS = {
    "low_latency": {
        "codec": "h264_fast",
        "quality": "fast",
        "frame_rate": 20,
        "buffer_size": 3,
        "chunk_duration": 2.0,
        "low_latency": True,
        "tts_options": {"speed": 175}  # Faster speech
    },
    "balanced": {
        "codec": "h264_medium",
        "quality": "medium",
        "frame_rate": 25,
        "buffer_size": 5,
        "chunk_duration": 3.0,
        "low_latency": False,
        "tts_options": {"speed": 150}
    },
    "high_quality": {
        "codec": "h264_high",
        "quality": "high",
        "frame_rate": 30,
        "buffer_size": 8,
        "chunk_duration": 4.0,
        "low_latency": False,
        "tts_options": {"speed": 140}
    }
}


def sanitize_options(endpoint: str, data: dict) -> dict:
    """
    Sanitize user input against allowed options.

    - Keeps only allowed keys
    - Uses defaults if missing
    - Converts type where necessary
    - Applies validation rules
    - Handles both old PHP format and new format
    """
    if endpoint not in ALLOWED_OPTIONS:
        raise ValueError(f"Unknown endpoint: {endpoint}")

    clean = {}
    for key, default in ALLOWED_OPTIONS[endpoint].items():
        value = data.get(key, default)

        # Type conversions based on default type
        if isinstance(default, bool):
            value = bool(value)
        elif isinstance(default, int):
            value = int(value)
        elif isinstance(default, float):
            value = float(value)
        elif isinstance(default, str):
            value = str(value)
        elif isinstance(default, dict):
            value = dict(value) if isinstance(value, dict) else default

        # Apply validation rules if they exist
        if key in VALIDATION_RULES:
            if value not in VALIDATION_RULES[key]:
                value = default  # Fall back to default if invalid

        clean[key] = value

    # Handle PHP format mapping for generate_avatar endpoint
    if endpoint == "generate_avatar":
        # Map PHP-style TTS parameters to the expected format
        if "tts_voice" in data:
            clean["voice"] = data["tts_voice"]
        if "tts_speed" in data:
            clean["rate"] = float(data["tts_speed"]) / 100.0  # Convert speed to rate
        if "tts_pitch" in data:
            clean["pitch"] = data["tts_pitch"]

        # Also preserve the original tts_engine from the request
        if "tts_engine" in data:
            clean["tts_engine"] = data["tts_engine"]  # Don't override with default

    # Post-processing for streaming endpoints
    if endpoint == "stream_avatar":
        clean = _post_process_streaming_options(clean)

    return clean


def _post_process_streaming_options(options: dict) -> dict:
    """
    Post-process streaming options for consistency and optimization
    """
    # Apply preset if low_latency is enabled
    if options.get("low_latency", False):
        preset = STREAMING_PRESETS["low_latency"]
        for key, value in preset.items():
            if key not in ["low_latency"]:  # Don't override the flag itself
                options[key] = value

    # Validate duration constraints
    chunk_duration = options.get("chunk_duration", 3.0)
    max_duration = options.get("max_duration", 300.0)

    # Ensure chunk_duration is reasonable
    if chunk_duration < 1.0:
        options["chunk_duration"] = 1.0
    elif chunk_duration > 10.0:
        options["chunk_duration"] = 10.0

    # Ensure max_duration is reasonable
    if max_duration < 10.0:
        options["max_duration"] = 10.0
    elif max_duration > 600.0:  # 10 minute hard limit
        options["max_duration"] = 600.0

    # Validate buffer_size
    buffer_size = options.get("buffer_size", 5)
    if buffer_size < 1:
        options["buffer_size"] = 1
    elif buffer_size > 20:
        options["buffer_size"] = 20

    # Auto-adjust frame rate for realtime mode
    if options.get("streaming_mode") == "realtime":
        frame_rate = options.get("frame_rate", 25)
        if frame_rate > 30:
            options["frame_rate"] = 30  # Cap realtime at 30fps

    # Ensure TTS options are valid for the selected engine
    tts_engine = options.get("tts_engine", "espeak")
    tts_options = options.get("tts_options", {})
    options["tts_options"] = _validate_tts_options(tts_engine, tts_options)

    return options


def _validate_tts_options(engine: str, options: dict) -> dict:
    """
    Validate TTS options for specific engines
    """
    if engine == "espeak":
        valid_keys = ["voice", "speed", "pitch", "amplitude", "word_gap"]
        filtered = {k: v for k, v in options.items() if k in valid_keys}

        # Validate ranges
        if "speed" in filtered:
            filtered["speed"] = max(80, min(400, int(filtered["speed"])))
        if "pitch" in filtered:
            filtered["pitch"] = max(0, min(99, int(filtered["pitch"])))
        if "amplitude" in filtered:
            filtered["amplitude"] = max(0, min(200, int(filtered["amplitude"])))

        return filtered

    elif engine == "festival":
        valid_keys = ["voice", "rate"]
        return {k: v for k, v in options.items() if k in valid_keys}

    # For other engines, return as-is (basic validation)
    return options


def validate_streaming_request(data: dict) -> tuple[bool, str]:
    """
    Validate a streaming request and return (is_valid, error_message)
    """
    # Check required fields
    if not data.get("prompt"):
        return False, "Prompt is required"

    # Check prompt length
    prompt = data.get("prompt", "")
    if len(prompt) > 5000:  # 5000 character limit for streaming
        return False, "Prompt too long (max 5000 characters for streaming)"

    if len(prompt) < 3:
        return False, "Prompt too short (minimum 3 characters)"

    # Check streaming mode
    streaming_mode = data.get("streaming_mode", "chunked")
    if streaming_mode not in VALIDATION_RULES["streaming_mode"]:
        return False, f"Invalid streaming_mode. Must be one of: {VALIDATION_RULES['streaming_mode']}"

    # Check frame rate for realtime mode
    if streaming_mode == "realtime":
        frame_rate = data.get("frame_rate", 30)
        if frame_rate > 30:
            return False, "Frame rate too high for realtime mode (max 30 fps)"

    # Check resource limits
    chunk_duration = data.get("chunk_duration", 3.0)
    max_duration = data.get("max_duration", 300.0)

    estimated_chunks = max_duration / chunk_duration
    if estimated_chunks > 100:
        return False, "Too many chunks estimated (reduce max_duration or increase chunk_duration)"

    return True, ""


def get_streaming_preset(preset_name: str) -> dict:
    """
    Get a streaming preset configuration
    """
    if preset_name in STREAMING_PRESETS:
        return STREAMING_PRESETS[preset_name].copy()

    return STREAMING_PRESETS["balanced"].copy()  # Default


def apply_streaming_preset(options: dict, preset_name: str) -> dict:
    """
    Apply a streaming preset to existing options
    """
    preset = get_streaming_preset(preset_name)
    updated_options = options.copy()
    updated_options.update(preset)
    return updated_options


def optimize_for_mobile(options: dict) -> dict:
    """
    Optimize streaming options for mobile clients
    """
    mobile_optimized = options.copy()

    # Lower settings for mobile
    mobile_optimized.update({
        "codec": "h264_fast",
        "quality": "fast",
        "frame_rate": 20,
        "chunk_duration": 2.5,
        "buffer_size": 3,
        "low_latency": True
    })

    return mobile_optimized


def get_endpoint_info(endpoint: str) -> dict:
    """
    Get information about an endpoint's options
    """
    if endpoint not in ALLOWED_OPTIONS:
        return {"error": f"Unknown endpoint: {endpoint}"}

    options = ALLOWED_OPTIONS[endpoint]
    info = {
        "endpoint": endpoint,
        "total_options": len(options),
        "options": {}
    }

    for key, default in options.items():
        option_info = {
            "default": default,
            "type": type(default).__name__
        }

        # Add validation info if available
        if key in VALIDATION_RULES:
            option_info["allowed_values"] = VALIDATION_RULES[key]

        info["options"][key] = option_info

    return info