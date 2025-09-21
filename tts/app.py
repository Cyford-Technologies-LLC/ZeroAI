from flask import Flask, send_file, request
from piper_tts_py import Piper
import io
import os

app = Flask(__name__)

# Initialize Piper
# You must download a Piper model and place it in the tts directory.
# Example: en_US-amy-medium.onnx and en_US-amy-medium.onnx.json
MODEL_PATH = "en_US-amy-medium.onnx"
CONFIG_PATH = "en_US-amy-medium.onnx.json"

if not os.path.exists(MODEL_PATH) or not os.path.exists(CONFIG_PATH):
    raise FileNotFoundError(
        f"Model files not found. Please download {MODEL_PATH} and {CONFIG_PATH} into the tts/ directory.")

piper = Piper(model_path=MODEL_PATH, config_path=CONFIG_PATH)


@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text')
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Create an in-memory file object for the audio
        wav_fp = io.BytesIO()
        piper.synthesize(text, wav_fp)
        wav_fp.seek(0)

        # Return the in-memory audio file
        return send_file(wav_fp, mimetype='audio/wav')

    except Exception as e:
        return {"error": str(e)}, 500


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
