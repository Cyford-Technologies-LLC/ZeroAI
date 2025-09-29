// config.js - Complete configuration file

// API Configuration
const CONFIG = {
    API_BASE_URL: '/web/api/',
    DEFAULT_TTS_ENGINE: 'espeak',
    DEFAULT_VOICE: 'en',
    CHUNK_TIMEOUT: 30000
};

// Debug levels
const DEBUG_LEVELS = {
    ERROR: 0,
    WARN: 1,
    INFO: 2,
    DEBUG: 3
};

// Global state variables
let debugMode = true;
let currentMode = 'simple';
let currentImageData = null;
let selectedPeer = null;
let generationInProgress = false;
let avatarStreamProcessor = null;

// TTS Engine configurations - THIS WAS MISSING!
const TTS_ENGINES = {
    espeak: {
        name: 'eSpeak (Free)',
        voices: [
            { value: 'en', label: 'English (Default)' },
            { value: 'en+f3', label: 'English Female 3' },
            { value: 'en+f4', label: 'English Female 4' },
            { value: 'en+m3', label: 'English Male 3' },
            { value: 'en+m4', label: 'English Male 4' },
            { value: 'en+f5', label: 'English Female 5' },
            { value: 'en+m5', label: 'English Male 5' }
        ],
        speedRange: [80, 400],
        pitchRange: [0, 99]
    },
    edge: {
        name: 'Microsoft Edge TTS',
        voices: [
            { value: 'en-US-AriaNeural', label: 'Aria (Female)' },
            { value: 'en-US-JennyNeural', label: 'Jenny (Female)' },
            { value: 'en-US-GuyNeural', label: 'Guy (Male)' },
            { value: 'en-US-DavisNeural', label: 'Davis (Male)' },
            { value: 'en-US-JaneNeural', label: 'Jane (Female)' },
            { value: 'en-US-JasonNeural', label: 'Jason (Male)' },
            { value: 'en-US-SaraNeural', label: 'Sara (Female)' },
            { value: 'en-US-TonyNeural', label: 'Tony (Male)' }
        ],
        speedRange: [50, 200],
        pitchRange: [-50, 50]
    },
    elevenlabs: {
        name: 'ElevenLabs (Premium)',
        voices: [
            { value: 'rachel', label: 'Rachel (Female)' },
            { value: 'domi', label: 'Domi (Female)' },
            { value: 'bella', label: 'Bella (Female)' },
            { value: 'antoni', label: 'Antoni (Male)' },
            { value: 'elli', label: 'Elli (Female)' },
            { value: 'josh', label: 'Josh (Male)' },
            { value: 'arnold', label: 'Arnold (Male)' },
            { value: 'adam', label: 'Adam (Male)' },
            { value: 'sam', label: 'Sam (Male)' }
        ],
        speedRange: [50, 150],
        pitchRange: [0, 0]
    },
    openai: {
        name: 'OpenAI TTS',
        voices: [
            { value: 'alloy', label: 'Alloy (Neutral)' },
            { value: 'echo', label: 'Echo (Male)' },
            { value: 'fable', label: 'Fable (Female)' },
            { value: 'onyx', label: 'Onyx (Male)' },
            { value: 'nova', label: 'Nova (Female)' },
            { value: 'shimmer', label: 'Shimmer (Female)' }
        ],
        speedRange: [25, 400],
        pitchRange: [0, 0]
    },
    coqui: {
        name: 'Coqui TTS',
        voices: [
            { value: 'female', label: 'Female (Default)' },
            { value: 'male', label: 'Male (Default)' },
            { value: 'female_emotional', label: 'Female (Emotional)' },
            { value: 'male_emotional', label: 'Male (Emotional)' }
        ],
        speedRange: [50, 200],
        pitchRange: [-50, 50]
    }
};

// Log that config is loaded
console.log('✅ Config loaded with TTS_ENGINES:', Object.keys(TTS_ENGINES));
console.log('✅ Global variables initialized');

// Export for debugging
window.CONFIG = CONFIG;
window.TTS_ENGINES = TTS_ENGINES;
window.DEBUG_LEVELS = DEBUG_LEVELS;