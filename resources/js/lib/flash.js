const DISMISS_AFTER_MS = 3200
const FADE_MS = 200

export function dismissible(flash) {
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

export function showFlash(type, message) {
    const template = document.querySelector(`template[data-flash-template="${type}"]`)
    const flash = template?.content.firstElementChild?.cloneNode(true)
    const text = flash?.querySelector('[data-flash-message]')
    if (!flash || !text) return

    document.body.append(flash)
    requestAnimationFrame(() => {
        text.textContent = message
    })
    dismissible(flash)
}
