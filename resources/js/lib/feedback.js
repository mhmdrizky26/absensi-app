/**
 * Bunyi dan getar sebagai tanda hasil scan, supaya guru tidak perlu melihat layar.
 */
let audioContext = null;

/**
 * Browser hanya mengizinkan suara setelah pengguna mengetuk layar.
 */
export function unlockAudio() {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;

    if (!audioContext && AudioContextClass) {
        audioContext = new AudioContextClass();
    }

    audioContext?.resume?.();
}

function tone(frequency, startOffset, duration) {
    if (!audioContext) {
        return;
    }

    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    const start = audioContext.currentTime + startOffset;

    oscillator.type = 'square';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.08, start);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start(start);
    oscillator.stop(start + duration);
}

const PATTERNS = {
    ok: { tones: [[1320, 0, 0.12]], vibrate: 60 },
    duplicate: { tones: [[880, 0, 0.08], [880, 0.12, 0.08]], vibrate: [40, 60, 40] },
    error: { tones: [[220, 0, 0.35]], vibrate: [200, 80, 200] },
};

export function signal(kind) {
    const pattern = PATTERNS[kind] ?? PATTERNS.error;

    pattern.tones.forEach(([frequency, offset, duration]) => tone(frequency, offset, duration));
    navigator.vibrate?.(pattern.vibrate);
}
