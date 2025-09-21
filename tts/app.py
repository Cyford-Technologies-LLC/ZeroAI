from flask import Flask, send_file, request
import subprocess
import tempfile
import os

app = Flask(__name__)


@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text')
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Use system espeak command if available
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp_file:
            cmd = ['espeak', '-w', tmp_file.name, text]
            subprocess.run(cmd, check=True)

            with open(tmp_file.name, 'rb') as f:
                audio_data = f.read()

            os.unlink(tmp_file.name)
            return send_file(io.BytesIO(audio_data), mimetype='audio/wav')

    except Exception as e:
        return {"error": str(e)}, 500


@app.route('/health')
def health():
    return {"status": "ok", "service": "TTS"}


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)