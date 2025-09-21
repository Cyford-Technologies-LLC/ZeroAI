from flask import Flask, send_file, request
import io

app = Flask(__name__)


@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text') if request.json else None
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Create a simple WAV header manually
        sample_rate = 44100
        duration = 2
        num_samples = sample_rate * duration

        # WAV file header
        wav_data = bytearray()
        wav_data.extend(b'RIFF')
        wav_data.extend((36 + num_samples * 2).to_bytes(4, 'little'))
        wav_data.extend(b'WAVE')
        wav_data.extend(b'fmt ')
        wav_data.extend((16).to_bytes(4, 'little'))
        wav_data.extend((1).to_bytes(2, 'little'))
        wav_data.extend((1).to_bytes(2, 'little'))
        wav_data.extend(sample_rate.to_bytes(4, 'little'))
        wav_data.extend((sample_rate * 2).to_bytes(4, 'little'))
        wav_data.extend((2).to_bytes(2, 'little'))
        wav_data.extend((16).to_bytes(2, 'little'))
        wav_data.extend(b'data')
        wav_data.extend((num_samples * 2).to_bytes(4, 'little'))

        # Add simple audio data (silence)
        for i in range(num_samples):
            wav_data.extend((0).to_bytes(2, 'little', signed=True))

        return send_file(io.BytesIO(wav_data), mimetype='audio/wav')

    except Exception as e:
        return {"error": str(e)}, 500


@app.route('/health', methods=['GET'])
def health():
    return {"status": "ok", "service": "TTS"}, 200


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
