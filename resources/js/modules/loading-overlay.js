const IGNORED_PROTOCOLS = ['mailto:', 'tel:', 'javascript:']

export function initLoadingOverlay() {
    const overlay = document.querySelector('[data-loading-overlay]')
    if (!overlay) return

    const showUnlessCancelled = (event) => {
        setTimeout(() => {
            if (!event.defaultPrevented) overlay.hidden = false
        }, 0)
    }

    document.addEventListener('submit', (event) => {
        if (event.target.hasAttribute('data-loading-skip')) return
        showUnlessCancelled(event)
    })

    document.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

        const link = event.target.closest('a[href]')
        if (!link || !isNavigatingLink(link)) return

        showUnlessCancelled(event)
    })

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) overlay.hidden = true
    })
}

function isNavigatingLink(link) {
    if (link.hasAttribute('data-loading-skip') || link.hasAttribute('download')) return false

    const target = link.getAttribute('target')
    if (target && target !== '_self') return false

    if (link.getAttribute('href').startsWith('#')) return false

    const url = new URL(link.href, window.location.href)
    if (IGNORED_PROTOCOLS.includes(url.protocol)) return false

    const withoutHash = (href) => href.split('#')[0]
    if (url.hash && withoutHash(url.href) === withoutHash(window.location.href)) return false

    return true
}
