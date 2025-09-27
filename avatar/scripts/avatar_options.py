# avatar/avatar_options.py

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

    # Streaming endpoint options
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



        # SadTalker-specific video options
        "timeout": 1200,
        "enhancer": None,
        "split_chunks": False,
        "chunk_length": 10,
        "fps": 15,
        "still": True,
        "preprocess": "crop",  # crop | none | resize
        "resolution": "256",



    },

    # Debug endpoints
    "debug": {
        "status": True,
        "logs": True,
        "reload": True,
        "health": True,
        "streaming": True,
        "metrics": True,
    }
}

# Validation rules for specific options
VALIDATION_RULES = {
    "streaming_mode": ["chunked", "realtime"],
    "codec": ["h264_high", "h264_medium", "h264_fast", "h265_high", "webm_high", "webm_fast"],
    "quality": ["high", "medium", "fast"],
    "tts_engine": ["espeak", "festival", "pico", "flite", "edge", "elevenlabs", "openai", "coqui"],
    "frame_rate": [12, 15, 20, 24, 25, 30, 60],
    "mode": ["simple", "sadtalker"],
    "preprocess": ["crop", "none", "resize"],
}

# TTS Engine specific voice mappings
TTS_ENGINE_VOICES = {
    "espeak": {
        "en": "English Default",
        "en+f3": "English Female 3",
        "en+f4": "English Female 4",
        "en+m3": "English Male 3",
        "en+m4": "English Male 4"
    },
    "edge": {
        "en-US-AriaNeural": "Aria (Female)",
        "en-US-JennyNeural": "Jenny (Female)",
        "en-US-GuyNeural": "Guy (Male)",
        "en-US-DavisNeural": "Davis (Male)",
        "en-US-JaneNeural": "Jane (Female)",
        "en-US-JasonNeural": "Jason (Male)"
    },
    "elevenlabs": {
        "21m00Tcm4TlvDq8ikWAM": "Rachel (Female)",
        "AZnzlk1XvdvUeBnXmlld": "Domi (Female)",
        "EXAVITQu4vr4xnSDxMaL": "Bella (Female)",
        "ErXwobaYiN019PkySvjV": "Antoni (Male)",
        "MF3mGyEYCl7XYWbV9V6O": "Elli (Female)",
        "TxGEqnHWrfWFTfGW9XjX": "Josh (Male)"
    },
    "openai": {
        "alloy": "Alloy (Neutral)",
        "echo": "Echo (Male)",
        "fable": "Fable (British Male)",
        "onyx": "Onyx (Male)",
        "nova": "Nova (Female)",
        "shimmer": "Shimmer (Female)"
    }
}


def sanitize_options(endpoint: str, data: dict) -> dict:
    """
    Sanitize user input against allowed options.
    Handles both old PHP format and new format
    """
    if endpoint not in ALLOWED_OPTIONS:
        raise ValueError(f"Unknown endpoint: {endpoint}")

    # Start with defaults
    clean = {}
    for key, default in ALLOWED_OPTIONS[endpoint].items():
        clean[key] = default

    # Override with actual request data (preserve original values)
    for key, value in data.items():
        if key in ALLOWED_OPTIONS[endpoint]:
            # Type conversion based on expected type
            expected_type = type(ALLOWED_OPTIONS[endpoint][key])
            if expected_type == bool:
                clean[key] = bool(value)
            elif expected_type == int:
                clean[key] = int(value)
            elif expected_type == float:
                clean[key] = float(value)
            elif expected_type == str:
                clean[key] = str(value)
            elif expected_type == dict:
                clean[key] = dict(value) if isinstance(value, dict) else default
            else:
                clean[key] = value

    # Handle PHP format mapping for generate_avatar endpoint
    if endpoint == "generate_avatar":
        # Map PHP-style TTS parameters - THESE OVERRIDE DEFAULTS
        if "tts_voice" in data:
            clean["voice"] = data["tts_voice"]  # en-US-JennyNeural
        if "tts_speed" in data:
            clean["rate"] = float(data["tts_speed"]) / 100.0  # 160 -> 1.6
        if "tts_pitch" in data:
            clean["pitch"] = data["tts_pitch"]
        if "tts_engine" in data:
            clean["tts_engine"] = data["tts_engine"]  # edge
        if "tts_language" in data:
            clean["language"] = data["tts_language"]

        # Debug logging
        print(f"DEBUG: Original request data: {data}")
        print(f"DEBUG: After sanitization: {clean}")
        print(f"DEBUG: TTS mapping - voice: {clean.get('voice')}, engine: {clean.get('tts_engine')}")

    # Post-processing for streaming endpoints
    if endpoint == "stream_avatar":
        clean = _post_process_streaming_options(clean)
        if "fps" in data:
            clean["frame_rate"] = int(data["fps"])


    return clean


def _post_process_streaming_options(options: dict) -> dict:
    """
    Post-process streaming options for consistency and optimization
    """
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

    return True, ""


def get_streaming_preset(preset_name: str) -> dict:
    """
    Get a streaming preset configuration
    """
    STREAMING_PRESETS = {
        "low_latency": {
            "codec": "h264_fast",
            "quality": "fast",
            "frame_rate": 20,
            "buffer_size": 3,
            "chunk_duration": 2.0,
            "low_latency": True
        },
        "balanced": {
            "codec": "h264_medium",
            "quality": "medium",
            "frame_rate": 25,
            "buffer_size": 5,
            "chunk_duration": 3.0,
            "low_latency": False
        },
        "high_quality": {
            "codec": "h264_high",
            "quality": "high",
            "frame_rate": 30,
            "buffer_size": 8,
            "chunk_duration": 4.0,
            "low_latency": False
        }
    }

    return STREAMING_PRESETS.get(preset_name, STREAMING_PRESETS["balanced"])


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


def validate_tts_voice(engine: str, voice: str) -> bool:
    """
    Validate that a voice is supported by the given TTS engine
    """
    if engine not in TTS_ENGINE_VOICES:
        return True  # Unknown engine, let it pass through

    return voice in TTS_ENGINE_VOICES[engine]


def get_engine_voices(engine: str) -> dict:
    """
    Get available voices for a TTS engine
    """
    return TTS_ENGINE_VOICES.get(engine, {})