import { replay } from '../lib/replay'

export function initGallery() {
    document.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

        const thumb = event.target.closest('[data-gallery-thumb]')
        const gallery = thumb?.closest('[data-gallery]')
        const main = gallery?.querySelector('[data-gallery-main]')
        if (!main) return

        event.preventDefault()
        if (main.src !== thumb.href) {
            main.addEventListener('load', () => replay(main, 'data-swapped'), { once: true })
        }
        main.src = thumb.href
        main.alt = thumb.querySelector('img')?.alt ?? main.alt

        gallery.querySelectorAll('[data-gallery-thumb][aria-current]').forEach((other) => other.removeAttribute('aria-current'))
        thumb.setAttribute('aria-current', 'true')
    })
}
