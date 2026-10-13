import { api } from '../lib/api'

export function initCookieBanner() {
    const banner = document.querySelector('[data-cookie-banner]')
    if (!banner) return

    const root = document.documentElement
    const observer = new ResizeObserver(() => root.style.setProperty('--cookie-banner-h', `${banner.offsetHeight}px`))
    observer.observe(banner)

    const form = banner.querySelector('form')
    if (!form) return

    form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) return
        event.preventDefault()

        try {
            await api(form.action, { method: 'POST' })
        } catch {
            form.submit()
            return
        }

        observer.disconnect()
        root.style.removeProperty('--cookie-banner-h')
        banner.remove()
        document.getElementById('main')?.focus()
    })
}
