# complete_audio2face_endpoints.py - Add these to your avatar_api.py

# Import the enhanced modules at the top of your file
from audio2face_options import Audio2FaceOptions, prepare_audio2face_request
from enhanced_audio2face_integration import (
    EnhancedAudio2FaceGenerator,
    generate_enhanced_audio2face_avatar
)

# ============================================================================
# COMPLETE AUDIO2FACE ENDPOINTS WITH ALL OPTIONS
# ============================================================================

@app.route('/generate/audio2face', methods=['POST'])
def generate_audio2face_avatar_complete():
    """
    Generate talking avatar with complete Audio2Face options

    Request body can include:
    - prompt (required): Text to speak
    - tts_engine: TTS engine to use
    - tts_options: TTS configuration
    - preset: Use a preset configuration ('high_quality', 'fast_preview', 'streaming', 'emotional')
    - a2f_options: Complete nested options structure
    - force_mock: Force mock mode for testing

    Or use flat structure for individual options:
    - animation: Animation settings
    - emotion: Emotion parameters
    - audio: Audio processing options
    - character: Character settings
    - post_processing: Post-processing options
    - performance: Performance settings
    - output: Output configuration
    - advanced: Advanced features
    """
    try:
        data = request.json or {}
        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        # Validate prompt
        prompt = clean_text(data.get("prompt", ""))
        if not prompt:
            return jsonify({"error": "Prompt is required"}), 400

        # Prepare complete request
        prepared_request = prepare_audio2face_request(data)

        # Check for validation errors
        if prepared_request.get("validation_errors"):
            logger.warning(f"Option validation warnings: {prepared_request['validation_errors']}")

        # Extract parameters
        tts_engine = prepared_request["tts_engine"]
        tts_options = prepared_request["tts_options"]
        a2f_options = prepared_request["a2f_options"]
        force_mock = prepared_request["force_mock"]
        a2f_server_url = prepared_request["a2f_server_url"]
        preset = data.get("preset")

        # Log the request
        logger.info(f"=== AUDIO2FACE GENERATION REQUEST ===")
        logger.info(f"Prompt: {prompt[:50]}...")
        logger.info(f"Preset: {preset}")
        logger.info(f"Force Mock: {force_mock}")
        logger.info(f"Options: {json.dumps(a2f_options, indent=2)}")

        # Generate output path
        timestamp = int(time.time())
        request_id = f"{timestamp}_{hash(prompt) & 0xFFFF:04x}"
        output_filename = f"audio2face_{request_id}.mp4"
        output_path = f"/app/static/{output_filename}"

        # Determine mode (real vs mock)
        use_mock = force_mock
        mode_info = {"type": "unknown", "reason": ""}

        if not use_mock:
            # Check if real A2F is available
            try:
                a2f = EnhancedAudio2FaceGenerator(a2f_server_url)
                if a2f.is_connected:
                    use_mock = False
                    mode_info = {"type": "real_enhanced", "reason": "Enhanced A2F server available"}
                else:
                    use_mock = True
                    mode_info = {"type": "mock", "reason": "A2F server not reachable"}
            except:
                use_mock = True
                mode_info = {"type": "mock", "reason": "A2F check failed"}
        else:
            mode_info = {"type": "mock", "reason": "Force mock enabled"}

        logger.info(f"Mode: {mode_info['type']} - {mode_info['reason']}")

        # Generate based on mode
        if use_mock:
            # Use mock for testing
            from mock_audio2face import generate_mock_audio2face_avatar

            character = a2f_options.get("character", {}).get("character_preset", "MockCharacter_Female_01")
            success = generate_mock_audio2face_avatar(
                prompt=prompt,
                source_image="",
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                character_path=character
            )
            metadata = {"mode": "mock", "character": character}
        else:
            # Use enhanced real A2F
            success, metadata = generate_enhanced_audio2face_avatar(
                prompt=prompt,
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                a2f_options=a2f_options,
                a2f_server_url=a2f_server_url,
                preset=preset
            )

        if success and os.path.exists(output_path):
            logger.info(f"✅ Generation completed: {output_filename}")

            # Prepare response with metadata
            response = send_file(output_path, mimetype='video/mp4', as_attachment=False)
            response.headers['X-Audio2Face-Mode'] = mode_info['type']
            response.headers['X-Audio2Face-Reason'] = mode_info['reason']
            response.headers['X-Request-ID'] = request_id

            # Add metadata as JSON header
            response.headers['X-Metadata'] = json.dumps(metadata)

            return response
        else:
            return jsonify({
                "error": "Audio2Face generation failed",
                "mode": mode_info,
                "metadata": metadata,
                "request_id": request_id
            }), 500

    except Exception as e:
        logger.error(f"Audio2Face endpoint error: {e}\n{traceback.format_exc()}")
        return jsonify({"error": str(e), "traceback": traceback.format_exc()}), 500


@app.route('/audio2face/options', methods=['GET'])
def get_audio2face_options():
    """Get documentation of all available Audio2Face options"""
    try:
        return jsonify({
            "options": Audio2FaceOptions.get_options_documentation(),
            "presets": {
                "high_quality": "Maximum quality with all features enabled",
                "fast_preview": "Quick generation for previews",
                "streaming": "Optimized for real-time streaming",
                "emotional": "Enhanced emotional expressions"
            },
            "categories": {
                "animation": "Core animation and lip-sync settings",
                "emotion": "Emotional expression parameters",
                "audio": "Audio processing and analysis",
                "character": "Character model and rendering",
                "post_processing": "Post-processing filters",
                "performance": "Performance optimization",
                "output": "Output format and quality",
                "advanced": "Advanced features and physics"
            }
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/validate', methods=['POST'])
def validate_audio2face_options():
    """Validate Audio2Face options before generation"""
    try:
        data = request.json or {}

        # Prepare and validate
        prepared = prepare_audio2face_request(data)
        is_valid, errors, sanitized = Audio2FaceOptions.validate_options(
            prepared.get("a2f_options", {})
        )

        return jsonify({
            "valid": is_valid,
            "errors": errors,
            "sanitized_options": sanitized,
            "warnings": prepared.get("validation_errors")
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/batch', methods=['POST'])
def batch_audio2face_generation():
    """
    Batch generate multiple Audio2Face avatars

    Request body:
    - prompts: List of prompt objects with text and optional option overrides
    - base_options: Base A2F options for all generations
    - preset: Optional preset to use as base
    """
    try:
        data = request.json or {}

        prompts = data.get("prompts", [])
        if not prompts:
            return jsonify({"error": "No prompts provided"}), 400

        # Prepare base options
        base_data = {
            "a2f_options": data.get("base_options", {}),
            "preset": data.get("preset"),
            "tts_engine": data.get("tts_engine", "espeak"),
            "tts_options": data.get("tts_options", {})
        }

        prepared = prepare_audio2face_request(base_data)
        base_options = prepared["a2f_options"]

        # Initialize generator
        a2f_server_url = data.get("a2f_server_url", "http://localhost:7860")
        a2f = EnhancedAudio2FaceGenerator(a2f_server_url)

        if not a2f.is_connected:
            return jsonify({
                "error": "Audio2Face server not connected",
                "suggestion": "Check server status or use force_mock for testing"
            }), 503

        # Generate batch
        batch_id = f"batch_{int(time.time())}"
        results = []

        for i, prompt_data in enumerate(prompts):
            try:
                if isinstance(prompt_data, str):
                    prompt_text = prompt_data
                    prompt_options = {}
                else:
                    prompt_text = prompt_data.get("text", "")
                    prompt_options = prompt_data.get("options", {})

                if not prompt_text:
                    results.append({"index": i, "error": "Empty prompt"})
                    continue

                # Merge options
                merged_options = base_options.copy()
                for key, value in prompt_options.items():
                    if key in merged_options and isinstance(merged_options[key], dict):
                        merged_options[key].update(value)
                    else:
                        merged_options[key] = value

                # Generate
                output_path = f"/app/static/{batch_id}_{i}.mp4"
                success, metadata = generate_enhanced_audio2face_avatar(
                    prompt=clean_text(prompt_text),
                    output_path=output_path,
                    tts_engine=prepared["tts_engine"],
                    tts_options=prepared["tts_options"],
                    a2f_options=merged_options,
                    a2f_server_url=a2f_server_url
                )

                if success:
                    results.append({
                        "index": i,
                        "success": True,
                        "output": f"/static/{batch_id}_{i}.mp4",
                        "metadata": metadata
                    })
                else:
                    results.append({
                        "index": i,
                        "success": False,
                        "error": metadata.get("error", "Generation failed")
                    })

            except Exception as e:
                results.append({
                    "index": i,
                    "error": str(e)
                })

        # Calculate statistics
        successful = sum(1 for r in results if r.get("success"))

        return jsonify({
            "batch_id": batch_id,
            "total": len(prompts),
            "successful": successful,
            "failed": len(prompts) - successful,
            "results": results
        })

    except Exception as e:
        logger.error(f"Batch generation error: {e}")
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/presets', methods=['GET'])
def list_audio2face_presets():
    """Get detailed information about available presets"""
    try:
        presets = {}

        for preset_name in ["high_quality", "fast_preview", "streaming", "emotional"]:
            preset_options = Audio2FaceOptions.create_preset(preset_name)
            presets[preset_name] = {
                "options": preset_options,
                "description": {
                    "high_quality": "Maximum quality with subdivision, wrinkles, and post-processing",
                    "fast_preview": "Quick generation without expensive effects",
                    "streaming": "Optimized for real-time with lower latency",
                    "emotional": "Enhanced emotional expressions and dynamics"
                }.get(preset_name, "")
            }

        return jsonify(presets)

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/capabilities', methods=['GET'])
def get_audio2face_capabilities():
    """Check server capabilities and available features"""
    try:
        a2f_server_url = request.args.get('server_url', 'http://localhost:7860')

        # Check real A2F
        try:
            a2f = EnhancedAudio2FaceGenerator(a2f_server_url)
            real_capabilities = {
                "available": a2f.is_connected,
                "server_url": a2f_server_url,
                "capabilities": a2f.server_capabilities
            }
        except:
            real_capabilities = {"available": False}

        # Check mock
        mock_available = False
        try:
            from mock_audio2face import MockAudio2FaceGenerator
            mock_available = True
        except:
            pass

        return jsonify({
            "real_audio2face": real_capabilities,
            "mock_audio2face": {"available": mock_available},
            "all_options": Audio2FaceOptions.get_options_documentation(),
            "endpoints": {
                "/generate/audio2face": "Main generation endpoint with all options",
                "/audio2face/options": "Get all available options documentation",
                "/audio2face/validate": "Validate options before generation",
                "/audio2face/batch": "Batch generate multiple avatars",
                "/audio2face/presets": "List available presets",
                "/audio2face/capabilities": "This endpoint"
            }
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500