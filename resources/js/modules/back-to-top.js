const HIDE_AFTER_IDLE_MS = 2000
const FADE_MS = 300

export function initBackToTop() {
    const button = document.querySelector('[data-back-to-top]')
    if (!button) return

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)')
    let idleTimer

    const show = () => {
        button.hidden = false
        requestAnimationFrame(() => {
            button.dataset.visible = ''
        })
    }

    const hide = () => {
        delete button.dataset.visible
        setTimeout(() => {
            if (!('visible' in button.dataset)) button.hidden = true
        }, FADE_MS)
    }

    window.addEventListener('scroll', () => {
        clearTimeout(idleTimer)

        if (window.scrollY > window.innerHeight) {
            show()
            idleTimer = setTimeout(hide, HIDE_AFTER_IDLE_MS)
        } else if (!button.hidden) {
            hide()
        }
    }, { passive: true })

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' })
    })
}
