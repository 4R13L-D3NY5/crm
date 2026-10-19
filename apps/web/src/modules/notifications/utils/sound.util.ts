/**
 * Web Audio API Synthesizer
 * Genera timbres y alertas armónicas nativas en el navegador sin depender de archivos de audio externos.
 */

export type NotificationSoundType = 'chime' | 'bell' | 'modern' | 'pop' | 'none'

let audioCtx: AudioContext | null = null
let hasUserInteracted = false

function initInteractionListener(): void {
  if (typeof window === 'undefined' || hasUserInteracted) return

  const unlockAudio = () => {
    hasUserInteracted = true
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume().catch(() => {})
    }
    window.removeEventListener('click', unlockAudio, true)
    window.removeEventListener('keydown', unlockAudio, true)
    window.removeEventListener('touchstart', unlockAudio, true)
    window.removeEventListener('pointerdown', unlockAudio, true)
  }

  window.addEventListener('click', unlockAudio, { once: true, passive: true, capture: true })
  window.addEventListener('keydown', unlockAudio, { once: true, passive: true, capture: true })
  window.addEventListener('touchstart', unlockAudio, { once: true, passive: true, capture: true })
  window.addEventListener('pointerdown', unlockAudio, { once: true, passive: true, capture: true })
}

if (typeof window !== 'undefined') {
  initInteractionListener()
}

function getAudioContext(): AudioContext | null {
  if (typeof window === 'undefined') return null

  // Si el usuario aún no interactuó con el DOM, no instanciamos ni forzamos resume()
  // para cumplir con la política de autoplay del navegador y no emitir advertencias en consola.
  if (!hasUserInteracted) return null

  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext
    if (AudioContextClass) {
      audioCtx = new AudioContextClass()
    }
  }
  if (audioCtx && audioCtx.state === 'suspended') {
    audioCtx.resume().catch(() => {
      // Navegador suspendió audio
    })
  }
  return audioCtx
}

export function playNotificationSound(soundType: string = 'chime', volumePercent: number = 80): void {
  if (!soundType || soundType === 'none') return

  try {
    const ctx = getAudioContext()
    if (!ctx) return

    const gain = ctx.createGain()
    const vol = (Math.max(0, Math.min(100, volumePercent)) / 100) * 0.35
    gain.gain.setValueAtTime(vol, ctx.currentTime)
    gain.connect(ctx.destination)

    const now = ctx.currentTime

    switch (soundType) {
      case 'chime': {
        // Doble acorde armónico C5 -> E5
        const osc1 = ctx.createOscillator()
        const osc2 = ctx.createOscillator()
        osc1.frequency.setValueAtTime(523.25, now) // C5
        osc2.frequency.setValueAtTime(659.25, now + 0.1) // E5
        osc1.connect(gain)
        osc2.connect(gain)
        osc1.start(now)
        osc1.stop(now + 0.2)
        osc2.start(now + 0.1)
        osc2.stop(now + 0.4)
        break
      }
      case 'bell': {
        // Campanilla clásica en triángulo A5
        const osc = ctx.createOscillator()
        osc.type = 'triangle'
        osc.frequency.setValueAtTime(880, now) // A5
        osc.connect(gain)
        osc.start(now)
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6)
        osc.stop(now + 0.6)
        break
      }
      case 'modern': {
        // Pulso doble dinámico
        const osc = ctx.createOscillator()
        osc.type = 'sine'
        osc.frequency.setValueAtTime(587.33, now) // D5
        osc.frequency.setValueAtTime(880, now + 0.09) // A5
        osc.connect(gain)
        osc.start(now)
        osc.stop(now + 0.25)
        break
      }
      case 'pop': {
        // Burbuja pop rápida
        const osc = ctx.createOscillator()
        osc.type = 'sine'
        osc.frequency.setValueAtTime(350, now)
        osc.frequency.exponentialRampToValueAtTime(850, now + 0.1)
        osc.connect(gain)
        osc.start(now)
        osc.stop(now + 0.12)
        break
      }
      default: {
        // Fallback chime
        const osc = ctx.createOscillator()
        osc.frequency.setValueAtTime(587.33, now)
        osc.connect(gain)
        osc.start(now)
        osc.stop(now + 0.2)
      }
    }
  } catch {
    // Si el usuario aún no interactuó con el DOM, el navegador ignora silenciosamente el audio
  }
}
