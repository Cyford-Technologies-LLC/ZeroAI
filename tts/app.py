from flask import Flask, send_file, request
from gtts import gTTS
import os
import io

app = Flask(__name__)

@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text')
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Create an in-memory file object for the audio
        mp3_fp = io.BytesIO()
        tts = gTTS(text=text, lang='en')
        tts.write_to_fp(mp3_fp)
        mp3_fp.seek(0)

        # Return the in-memory audio file
        return send_file(mp3_fp, mimetype='audio/mpeg')

    except Exception as e:
        return {"error": str(e)}, 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
