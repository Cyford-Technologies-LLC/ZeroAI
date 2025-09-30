# Add this to your avatar_endpoints.py - Smart Audio2Face endpoint

import time
import traceback

# Import both real and mock Audio2Face
try:
    from audio2face_integration import (
        generate_audio2face_avatar,
        check_audio2face_requirements,
        Audio2FaceGenerator
    )

    AUDIO2FACE_REAL_AVAILABLE = True
except ImportError:
    AUDIO2FACE_REAL_AVAILABLE = False

try:
    from mock_audio2face import (
        generate_mock_audio2face_avatar,
        MockAudio2FaceGenerator
    )

    AUDIO2FACE_MOCK_AVAILABLE = True
except ImportError:
    AUDIO2FACE_MOCK_AVAILABLE = False


# ============================================================================
# SMART AUDIO2FACE ENDPOINT - Automatically chooses real or mock
# ============================================================================

@app.route('/generate/audio2face', methods=['POST'])
def generate_audio2face_avatar_endpoint():
    """
    Generate talking avatar video using NVIDIA Audio2Face (real or mock).
    Automatically detects if real Audio2Face is available, falls back to mock.

    Required:
    - prompt: Text to speak

    Optional:
    - character_path: A2F character to use
    - tts_engine: TTS engine ('espeak', 'edge', etc.)
    - tts_options: TTS options (voice, rate, pitch, language)
    - force_mock: Force use of mock Audio2Face for testing
    """
    try:
        data = request.json or {}
        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        # Extract parameters
        prompt = clean_text(data.get("prompt", ""))
        if not prompt:
            return jsonify({"error": "Prompt is required"}), 400

        character_path = data.get("character_path")
        tts_engine = data.get("tts_engine", "espeak")
        force_mock = data.get("force_mock", False)

        # Handle TTS options
        tts_options = {}
        for key in ["voice", "rate", "pitch", "language"]:
            if key in data:
                tts_options[key] = data[key]

        # Determine which Audio2Face to use
        use_mock = force_mock
        mode_info = {"type": "unknown", "reason": ""}

        if not use_mock and AUDIO2FACE_REAL_AVAILABLE:
            # Check if real Audio2Face is actually working
            try:
                status = check_audio2face_requirements()
                if status['requirements_met']:
                    use_mock = False
                    mode_info = {"type": "real", "reason": "Audio2Face server available"}
                else:
                    use_mock = True
                    mode_info = {"type": "mock", "reason": "Audio2Face server not ready"}
            except:
                use_mock = True
                mode_info = {"type": "mock", "reason": "Audio2Face check failed"}
        else:
            use_mock = True
            if force_mock:
                mode_info = {"type": "mock", "reason": "Forced mock mode"}
            elif not AUDIO2FACE_REAL_AVAILABLE:
                mode_info = {"type": "mock", "reason": "Real Audio2Face not installed"}

        logger.info(f"=== AUDIO2FACE GENERATION START ===")
        logger.info(f"Mode: {mode_info['type']} ({mode_info['reason']})")
        logger.info(f"Prompt: {prompt[:50]}...")
        logger.info(f"Character: {character_path}")
        logger.info(f"TTS Engine: {tts_engine}, Options: {tts_options}")

        # Generate output path
        timestamp = int(time.time())
        mode_prefix = "mock_" if use_mock else "real_"
        output_filename = f"audio2face_{mode_prefix}{timestamp}.mp4"
        output_path = f"/app/static/{output_filename}"

        # Generate avatar
        if use_mock:
            if not AUDIO2FACE_MOCK_AVAILABLE:
                return jsonify({
                    "error": "Neither real nor mock Audio2Face available",
                    "suggestion": "Install audio2face_integration.py and mock_audio2face.py"
                }), 503

            success = generate_mock_audio2face_avatar(
                prompt=prompt,
                source_image="",
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                character_path=character_path
            )
        else:
            success = generate_audio2face_avatar(
                prompt=prompt,
                source_image="",
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                character_path=character_path,
                a2f_server_url=data.get("a2f_server_url", "http://localhost:7860")
            )

        if success and os.path.exists(output_path):
            logger.info(f"✅ Audio2Face generation completed: {output_filename}")

            # Return video with metadata
            response = send_file(output_path, mimetype='video/mp4', as_attachment=False)
            response.headers['X-Audio2Face-Mode'] = mode_info['type']
            response.headers['X-Audio2Face-Reason'] = mode_info['reason']
            response.headers['X-Character-Used'] = character_path or 'default'

            return response
        else:
            return jsonify({
                "error": f"Audio2Face generation failed ({mode_info['type']} mode)",
                "mode": mode_info,
                "suggestion": "Check server logs for details"
            }), 500

    except Exception as e:
        logger.error("Audio2Face generation error: %s\n%s", e, traceback.format_exc())
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/status')
def audio2face_status():
    """Check Audio2Face integration status - both real and mock"""
    try:
        status = {
            'timestamp': str(datetime.now()),
            'real_audio2face': {'available': False, 'status': {}},
            'mock_audio2face': {'available': AUDIO2FACE_MOCK_AVAILABLE},
            'recommended_mode': 'unknown'
        }

        # Check real Audio2Face
        if AUDIO2FACE_REAL_AVAILABLE:
            try:
                real_status = check_audio2face_requirements()
                status['real_audio2face'] = {
                    'available': True,
                    'status': real_status
                }

                if real_status['requirements_met']:
                    status['recommended_mode'] = 'real'
                else:
                    status['recommended_mode'] = 'mock'
            except Exception as e:
                status['real_audio2face'] = {
                    'available': False,
                    'error': str(e)
                }
                status['recommended_mode'] = 'mock'
        else:
            status['recommended_mode'] = 'mock' if AUDIO2FACE_MOCK_AVAILABLE else 'none'

        # Add usage info
        status['usage'] = {
            'endpoint': '/generate/audio2face',
            'method': 'POST',
            'required_params': ['prompt'],
            'optional_params': [
                'character_path', 'tts_engine', 'tts_options',
                'force_mock', 'a2f_server_url'
            ],
            'modes': {
                'real': 'Uses actual NVIDIA Audio2Face server',
                'mock': 'Uses mock Audio2Face for testing/fallback'
            }
        }

        return jsonify(status)

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/audio2face/characters')
def list_audio2face_characters():
    """List available characters from real or mock Audio2Face"""
    try:
        force_mock = request.args.get('force_mock', 'false').lower() == 'true'

        result = {
            'characters': [],
            'mode': 'unknown',
            'source': 'none'
        }

        # Try real Audio2Face first
        if not force_mock and AUDIO2FACE_REAL_AVAILABLE:
            try:
                a2f_server_url = request.args.get('server_url', 'http://localhost:7860')
                a2f = Audio2FaceGenerator(a2f_server_url)

                if a2f.is_connected:
                    characters = a2f.list_available_characters()
                    result = {
                        'characters': characters,
                        'count': len(characters),
                        'mode': 'real',
                        'source': a2f_server_url,
                        'current_character': a2f.current_character
                    }
                    return jsonify(result)
            except Exception as e:
                logger.warning(f"Real Audio2Face failed: {e}")

        # Fallback to mock
        if AUDIO2FACE_MOCK_AVAILABLE:
            mock_a2f = MockAudio2FaceGenerator()
            characters = mock_a2f.list_available_characters()
            result = {
                'characters': characters,
                'count': len(characters),
                'mode': 'mock',
                'source': 'mock_audio2face',
                'current_character': mock_a2f.current_character
            }
        else:
            result['error'] = 'No Audio2Face implementation available'

        return jsonify(result)

    except Exception as e:
        logger.error(f"Error listing characters: {e}")
        return jsonify({'error': str(e)}), 500


# Update your existing /generate endpoint to include audio2face mode
@app.route('/generate', methods=['POST'])
def generate_avatar():
    """Generate talking avatar video - UPDATED to include audio2face mode"""
    try:
        data = request.json or {}
        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        # Get mode from URL args or data - NOW INCLUDES audio2face
        mode = request.args.get('mode', data.get('mode', 'simple'))

        # If audio2face mode is requested, redirect to Audio2Face endpoint
        if mode == 'audio2face':
            logger.info("Redirecting to Audio2Face generation...")
            return generate_audio2face_avatar_endpoint()

        # Continue with your existing logic for other modes...
        # (rest of your existing generate_avatar function remains unchanged)

        # Extract parameters
        prompt = clean_text(data.get("prompt", ""))
        source_image_input = data.get("image")
        tts_engine = data.get("tts_engine", "espeak")

        # Handle TTS options
        tts_options = {}
        for key in ["voice", "rate", "pitch", "language"]:
            if key in data:
                tts_options[key] = data[key]

        codec = request.args.get('codec', data.get('codec', DEFAULT_CODEC))
        quality = request.args.get('quality', data.get('quality', 'high'))

        # Extract SadTalker options
        sadtalker_options = {
            "timeout": data.get("timeout", 1200),
            "enhancer": data.get("enhancer", None),
            "split_chunks": data.get("split_chunks", False),
            "chunk_length": data.get("chunk_length", 10)
        }

        logger.info(f"=== AVATAR GENERATION START ===")
        logger.info(f"Mode: {mode}, Codec: {codec}, Quality: {quality}")
        logger.info(f"Prompt: {prompt[:50]}...")

        # Load and validate image
        img = load_and_preprocess_image(source_image_input)
        if img is None:
            return jsonify({"error": "Image load failed"}), 500

        # Create temporary audio file
        with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as audio_file:
            audio_path = audio_file.name

        video_path = "/app/static/avatar_video.avi"

        try:
            # Generate TTS
            if not call_tts_service_with_options(prompt, audio_path, tts_engine, tts_options):
                return jsonify({"error": "TTS generation failed"}), 500

            audio_path = normalize_audio(audio_path)

            # Generate video based on mode
            if mode == "sadtalker":
                logger.info("=== ATTEMPTING SADTALKER MODE ===")
                success = generate_sadtalker_video(
                    audio_path,
                    video_path,
                    prompt,
                    codec,
                    quality,
                    **sadtalker_options,
                    source_image=source_image_input
                )

                if not success:
                    logger.info("=== SADTALKER FAILED - FALLBACK TO SIMPLE FACE ===")
                    generate_talking_face(source_image_input, audio_path, video_path, codec, quality)
            else:
                logger.info("=== USING SIMPLE/MEDIAPIPE MODE ===")
                generate_talking_face(source_image_input, audio_path, video_path, codec, quality)

            # Convert video with specified codec
            final_path = convert_video_with_codec(video_path, audio_path, codec, quality)
            if final_path and os.path.exists(final_path):
                return send_file(final_path, mimetype=get_mimetype_for_codec(codec), as_attachment=False)

            return jsonify({"error": "Video creation failed"}), 500

        finally:
            # Cleanup temporary files
            for temp_file in [audio_path, audio_path.replace('.wav', '_fixed.wav')]:
                if os.path.exists(temp_file):
                    try:
                        os.unlink(temp_file)
                    except:
                        pass

    except Exception as e:
        logger.error("Generation error: %s\n%s", e, traceback.format_exc())
        return jsonify({"error": str(e)}), 500