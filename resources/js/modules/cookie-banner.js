import { api } from '../lib/api'

export function initCookieBanner() {
    const banner = document.querySelector('[data-cookie-banner]')
    if (!banner) return

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

        banner.remove()
        document.getElementById('main')?.focus()
    })
}
