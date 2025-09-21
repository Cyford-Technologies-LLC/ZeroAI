cat > / app / tts / app.py << 'EOF'
from flask import Flask, send_file, request
import io
import wave
import struct
import math

app = Flask(__name__)


def generate_sine_wave(frequency=440, duration=1, sample_rate=44100):
    """Generate a sine wave audio"""
    frames = int(duration * sample_rate)
    audio_data = []

    for i in range(frames):
        value = int(32767 * math.sin(2 * math.pi * frequency * i / sample_rate))
        audio_data.append(struct.pack('
        return b''.join(audio_data)


@app.route('/synthesize', methods=['POST'])
def synthesize():
    text = request.json.get('text') if request.json else None
    if not text:
        return {"error": "No text provided"}, 400

    try:
        # Generate a simple tone (placeholder for actual TTS)
        duration = min(len(text) * 0.1, 3)  # Duration based on text length, max 3 seconds
        audio_data = generate_sine_wave(duration=duration)

        # Create WAV file in memory
        wav_buffer = io.BytesIO()
        with wave.open(wav_buffer, 'wb') as wav_file:
            wav_file.setnchannels(1)  # Mono
            wav_file.setsampwidth(2)  # 2 bytes per sample
            wav_file.setframerate(44100)  # Sample rate
            wav_file.writeframes(audio_data)

        wav_buffer.seek(0)
        return send_file(wav_buffer, mimetype='audio/wav', as_attachment=False)

    except Exception as e:
        print(f"Error: {e}")
        return {"error": str(e)}, 500


@app.route('/health', methods=['GET'])
def health():
    return {"status": "ok", "service": "TTS"}, 200


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)