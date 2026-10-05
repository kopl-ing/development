// Device alerts (`kopling.alert()`) and the screen wake lock (`[data-wake-lock]`), both following the
// person's own preferences from the `kopling-alert` meta tag (`Ux\Alert::preferences()`).
const SOUNDS = {
    beep: [[880, 0, 0.15, 'square'], [880, 0.25, 0.15, 'square']],
    chime: [[660, 0, 0.5, 'sine'], [990, 0.18, 0.7, 'sine']],
    whistle: [[2800, 0, 0.25, 'sine', 40], [2800, 0.35, 0.6, 'sine', 40]],
};

let audio = null;
let wakeLock = null;

function preferences() {
    try {
        return JSON.parse(document.querySelector('meta[name="kopling-alert"]')?.content ?? '{}');
    } catch {
        return {};
    }
}

// Browsers only start audio after a user gesture; creating the context on the first one lets a later timer play.
function unlockAudio() {
    if (!audio && window.AudioContext) {
        audio = new AudioContext();
    }
    if (audio?.state === 'suspended') {
        audio.resume();
    }
}

document.addEventListener('pointerdown', unlockAudio, { capture: true });
document.addEventListener('keydown', unlockAudio, { capture: true });

function play(name) {
    const notes = SOUNDS[name];
    if (!notes || !audio) {
        return;
    }

    if (navigator.audioSession) {
        navigator.audioSession.type = 'playback';
    }

    const start = audio.currentTime;
    for (const [frequency, offset, duration, type, trill] of notes) {
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        oscillator.type = type;
        oscillator.frequency.value = frequency;

        if (trill) {
            const lfo = audio.createOscillator();
            const depth = audio.createGain();
            lfo.frequency.value = trill;
            depth.gain.value = frequency * 0.06;
            lfo.connect(depth).connect(oscillator.frequency);
            lfo.start(start + offset);
            lfo.stop(start + offset + duration);
        }

        gain.gain.setValueAtTime(0.0001, start + offset);
        gain.gain.exponentialRampToValueAtTime(0.4, start + offset + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + duration);
        oscillator.connect(gain).connect(audio.destination);
        oscillator.start(start + offset);
        oscillator.stop(start + offset + duration);
    }
}

export function alert({ vibrate = [300, 150, 300], sound } = {}) {
    const prefs = preferences();

    if (prefs.vibrate !== false) {
        navigator.vibrate?.(vibrate);
    }

    play(sound ?? prefs.sound);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-alert-preview]');

    if (button) {
        unlockAudio();
        play(button.form?.elements[button.dataset.alertPreview]?.value);
    }
});

async function syncWakeLock() {
    const wanted = preferences().wakeLock !== false && document.querySelector('[data-wake-lock]') !== null;

    if (wanted && !wakeLock && 'wakeLock' in navigator && document.visibilityState === 'visible') {
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => {
                wakeLock = null;
            });
        } catch {}
    } else if (!wanted && wakeLock) {
        wakeLock.release();
    }
}

document.addEventListener('visibilitychange', syncWakeLock);
document.addEventListener('htmx:after:settle', syncWakeLock);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncWakeLock);
} else {
    syncWakeLock();
}
