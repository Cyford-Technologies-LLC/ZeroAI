from flask import Flask, send_file, request
import pyttsx3
import io
import tempfile
import os

app = Flask(__name__)


@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text')
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Initialize pyttsx3 engine
        engine = pyttsx3.init()

        # Optional: Set voice properties
        rate = engine.getProperty('rate')
        engine.setProperty('rate', rate - 50)  # Slower speech

        voices = engine.getProperty('voices')
        if voices:
            engine.setProperty('voice', voices[0].id)  # Use first available voice

        # Create temporary file
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp_file:
            temp_path = tmp_file.name

        # Save speech to file
        engine.save_to_file(text, temp_path)
        engine.runAndWait()

        # Read the file and return it
        if os.path.exists(temp_path):
            with open(temp_path, 'rb') as f:
                audio_data = f.read()

            # Cleanup temp file
            os.unlink(temp_path)

            return send_file(io.BytesIO(audio_data), mimetype='audio/wav')
        else:
            return {"error": "Failed to generate audio file"}, 500

    except Exception as e:
        return {"error": str(e)}, 500


@app.route('/health', methods=['GET'])
def health():
    return {"status": "ok", "service": "TTS"}, 200


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)