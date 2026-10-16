const STORAGE_KEY = 'music'
const ACTIVATION_EVENTS = ['pointerdown', 'keydown', 'touchend']

export function initMusic() {
    const toggle = document.querySelector('[data-music-toggle]')
    const audio = document.querySelector('[data-music-audio]')
    if (!toggle || !audio) return

    let enabled = readEnabled()

    const render = () => {
        toggle.setAttribute('aria-pressed', String(enabled))
        toggle.title = enabled ? toggle.dataset.labelOn : toggle.dataset.labelOff
        toggle.dataset.state = enabled ? 'on' : 'off'
        toggle.hidden = false
    }

    const stopWaiting = () => {
        ACTIVATION_EVENTS.forEach((type) => document.removeEventListener(type, onFirstInteraction, true))
    }

    const onFirstInteraction = (event) => {
        if (toggle.contains(event.target)) {
            stopWaiting()
            return
        }

        if (enabled) {
            audio.play().then(stopWaiting).catch(() => {})
        }
    }

    const waitForInteraction = () => {
        ACTIVATION_EVENTS.forEach((type) => document.addEventListener(type, onFirstInteraction, true))
    }

    toggle.addEventListener('click', () => {
        if (enabled && audio.paused) {
            audio.play().catch(() => {})
            return
        }

        enabled = !enabled
        writeEnabled(enabled)
        render()

        if (enabled) {
            audio.play().catch(() => {})
        } else {
            audio.pause()
        }
    })

    render()

    if (enabled) {
        audio.play().catch(waitForInteraction)
    }
}

function readEnabled() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) !== 'false'
    } catch {
        return true
    }
}

function writeEnabled(enabled) {
    try {
        window.localStorage.setItem(STORAGE_KEY, enabled ? 'true' : 'false')
    } catch {}
}
