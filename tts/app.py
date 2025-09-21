from flask import Flask, send_file, request
import subprocess
import tempfile
import os
import io

app = Flask(__name__)


@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text')
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Use espeak to generate speech
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp_file:
            cmd = ['espeak-ng', '-w', tmp_file.name, text]
            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode != 0:
                return {"error": f"espeak failed: {result.stderr}"}, 500

            # Read the generated audio file
            with open(tmp_file.name, 'rb') as f:
                audio_data = f.read()

            # Clean up temp file
            os.unlink(tmp_file.name)

            return send_file(io.BytesIO(audio_data), mimetype='audio/wav')

    except Exception as e:
        return {"error": str(e)}, 500


@app.route('/health', methods=['GET'])
def health():
    return {"status": "ok", "service": "TTS"}


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)