const DISMISS_AFTER_MS = 3200
const FADE_MS = 200

export function initFlash() {
    const flash = document.querySelector('[data-flash]')
    if (!flash) return

    let dismissed = false
    let timer

    const dismiss = () => {
        if (dismissed) return
        dismissed = true
        clearTimeout(timer)
        document.removeEventListener('click', dismiss, true)
        document.removeEventListener('keydown', dismiss, true)
        flash.dataset.state = 'leaving'
        setTimeout(() => flash.remove(), FADE_MS)
    }

    if (!flash.hasAttribute('data-flash-sticky')) {
        timer = setTimeout(dismiss, DISMISS_AFTER_MS)
    }

    document.addEventListener('click', dismiss, true)
    document.addEventListener('keydown', dismiss, true)
}
