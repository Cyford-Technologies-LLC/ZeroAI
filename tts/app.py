from flask import Flask, send_file, request, jsonify
import requests
import os
import asyncio
import edge_tts
import tempfile
import subprocess
from openai import OpenAI
import io

app = Flask(__name__)


class MultiTTSEngine:
    def __init__(self):
        try:
            from TTS.api import TTS
            self.coqui_tts = TTS(model_name="tts_models/en/ljspeech/tacotron2-DDC_ph")
            #self.coqui_tts = None
            print("Coqui TTS initialized")
        except Exception as e:
            print(f"Coqui TTS failed to initialize: {e}")
            self.coqui_tts = None

    def generate_espeak_tts(self, text, voice="en", speed=175):
        """espeak-ng TTS - Free tier (basic quality)"""
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp_file:
            cmd = ['espeak-ng', '-v', voice, '-s', str(speed), '-w', tmp_file.name, text]
            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode != 0:
                raise Exception(f"espeak failed: {result.stderr}")

            with open(tmp_file.name, 'rb') as f:
                audio_data = f.read()
            os.unlink(tmp_file.name)
        return audio_data

    def generate_edge_tts(self, text, voice="en-US-AriaNeural"):
        """Microsoft Edge TTS - Basic tier"""
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        try:
            return loop.run_until_complete(self.generate_edge_tts_async(text, voice))
        finally:
            loop.close()

    async def generate_edge_tts_async(self, text, voice):
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp_file:
            communicate = edge_tts.Communicate(text, voice)
            await communicate.save(tmp_file.name)
            with open(tmp_file.name, 'rb') as f:
                audio_data = f.read()
            os.unlink(tmp_file.name)
        return audio_data

    def generate_coqui_tts(self, text):
        """Coqui TTS - Pro tier"""
        if not self.coqui_tts:
            raise Exception("Coqui TTS not available")

        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp_file:
            self.coqui_tts.tts_to_file(text=text, file_path=tmp_file.name)
            with open(tmp_file.name, 'rb') as f:
                audio_data = f.read()
            os.unlink(tmp_file.name)
        return audio_data

    def generate_openai_tts(self, text, voice="alloy", api_key=None):
        """OpenAI TTS - Pro tier"""
        if not api_key:
            raise ValueError("OpenAI API key required")

        client = OpenAI(api_key=api_key)
        response = client.audio.speech.create(
            model="tts-1-hd",
            voice=voice,
            input=text
        )
        return response.content

    def generate_elevenlabs_tts(self, text, voice_id="21m00Tcm4TlvDq8ikWAM", api_key=None):
        """ElevenLabs TTS - Enterprise tier"""
        if not api_key:
            raise ValueError("ElevenLabs API key required")

        url = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}"
        headers = {
            "Accept": "audio/wav",
            "Content-Type": "application/json",
            "xi-api-key": api_key
        }
        data = {
            "text": text,
            "model_id": "eleven_monolingual_v1",
            "voice_settings": {"stability": 0.5, "similarity_boost": 0.5}
        }

        response = requests.post(url, json=data, headers=headers)
        if response.status_code != 200:
            raise Exception(f"ElevenLabs error: {response.text}")
        return response.content


# Initialize TTS engine
tts_engine = MultiTTSEngine()


@app.route('/synthesize', methods=['POST'])
def synthesize():
    data = request.get_json()
    text = data.get('text', '')
    engine = data.get('engine', 'espeak')  # Default to free tier

    if not text:
        return {"error": "No text provided"}, 400

    try:
        print(f"Generating TTS with engine: {engine}")

        if engine == 'espeak':
            voice = data.get('voice', 'en')
            speed = data.get('speed', 175)
            audio_data = tts_engine.generate_espeak_tts(text, voice, speed)

        elif engine == 'edge':
            voice = data.get('voice', 'en-US-AriaNeural')
            audio_data = tts_engine.generate_edge_tts(text, voice)

        elif engine == 'coqui':
            audio_data = tts_engine.generate_coqui_tts(text)

        elif engine == 'openai':
            api_key = data.get('api_key') or os.getenv('OPENAI_API_KEY')
            voice = data.get('voice', 'alloy')
            audio_data = tts_engine.generate_openai_tts(text, voice, api_key)

        elif engine == 'elevenlabs':
            api_key = data.get('api_key') or os.getenv('ELEVENLABS_API_KEY')
            voice_id = data.get('voice_id', '21m00Tcm4TlvDq8ikWAM')
            audio_data = tts_engine.generate_elevenlabs_tts(text, voice_id, api_key)

        else:
            return {"error": f"Unknown engine: {engine}"}, 400

        return send_file(io.BytesIO(audio_data), mimetype='audio/wav')

    except Exception as e:
        print(f"TTS Error: {e}")
        return {"error": str(e)}, 500


@app.route('/engines', methods=['GET'])
def get_engines():
    return jsonify({
        "engines": ["espeak", "edge", "coqui", "openai", "elevenlabs"],
        "tiers": {
            "free": "espeak",
            "basic": "edge",
            "pro": ["coqui", "openai"],
            "enterprise": "elevenlabs"
        }
    })


@app.route('/voices', methods=['GET'])
def get_voices():
    return jsonify({
        "espeak": ["en", "en-us", "en-gb", "fr", "es", "de"],
        "edge": ["en-US-AriaNeural", "en-US-JennyNeural", "en-US-GuyNeural", "en-US-ChristopherNeural"],
        "coqui": ["default"],
        "openai": ["alloy", "echo", "fable", "onyx", "nova", "shimmer"],
        "elevenlabs": ["21m00Tcm4TlvDq8ikWAM", "AZnzlk1XvdvUeBnXmlld", "EXAVITQu4vr4xnSDxMaL"]
    })


@app.route('/health', methods=['GET'])
def health():
    return {"status": "ok", "service": "Multi-TTS", "engines": 5}


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)