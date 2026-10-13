export function initGallery() {
    document.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

        const thumb = event.target.closest('[data-gallery-thumb]')
        const gallery = thumb?.closest('[data-gallery]')
        const main = gallery?.querySelector('[data-gallery-main]')
        if (!main) return

        event.preventDefault()
        main.src = thumb.href
        main.alt = thumb.querySelector('img')?.alt ?? main.alt

        gallery.querySelectorAll('[data-gallery-thumb][aria-current]').forEach((other) => other.removeAttribute('aria-current'))
        thumb.setAttribute('aria-current', 'true')
    })
}
