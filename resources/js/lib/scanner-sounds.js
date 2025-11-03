/**
 * Scanner Sound Effects using Web Audio API
 * Generates beep/buzz sounds without external audio files
 */

class ScannerSounds {
    constructor() {
        this.audioContext = null;
        this.enabled = true;
    }

    /**
     * Initialize Audio Context (lazy loading for user interaction requirement)
     */
    initAudioContext() {
        if (!this.audioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        return this.audioContext;
    }

    /**
     * Play a tone with specified frequency and duration
     */
    playTone(frequency, duration, type = 'sine') {
        if (!this.enabled) return;

        try {
            const ctx = this.initAudioContext();
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);

            oscillator.frequency.value = frequency;
            oscillator.type = type;

            // Envelope: attack and release to avoid clicks
            const now = ctx.currentTime;
            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(0.3, now + 0.01); // Attack
            gainNode.gain.linearRampToValueAtTime(0.3, now + duration - 0.01);
            gainNode.gain.linearRampToValueAtTime(0, now + duration); // Release

            oscillator.start(now);
            oscillator.stop(now + duration);
        } catch (error) {
            console.warn('Failed to play sound:', error);
        }
    }

    /**
     * Play success sound (positive beep)
     * High-pitched pleasant tone
     */
    playSuccess() {
        this.playTone(1000, 0.1, 'sine');
        
        // Double beep for success
        setTimeout(() => {
            this.playTone(1200, 0.08, 'sine');
        }, 120);
    }

    /**
     * Play error sound (negative buzz)
     * Low-pitched warning tone
     */
    playError() {
        this.playTone(200, 0.3, 'sawtooth');
    }

    /**
     * Play warning sound (neutral beep)
     */
    playWarning() {
        this.playTone(600, 0.15, 'triangle');
    }

    /**
     * Enable/disable sounds
     */
    setEnabled(enabled) {
        this.enabled = enabled;
    }

    /**
     * Cleanup
     */
    dispose() {
        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }
    }
}

// Singleton instance
let scannerSoundsInstance = null;

export function getScannerSounds() {
    if (!scannerSoundsInstance) {
        scannerSoundsInstance = new ScannerSounds();
    }
    return scannerSoundsInstance;
}

export default ScannerSounds;
