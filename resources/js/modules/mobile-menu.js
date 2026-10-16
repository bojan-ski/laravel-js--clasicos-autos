export function initMobileMenu() {
    const menu = document.querySelector('[data-mobile-menu]')
    if (!menu) return

    const close = () => {
        menu.open = false
    }

    const header = menu.closest('header')
    const pageBehind = [...document.body.children].filter((element) => element !== header)

    menu.addEventListener('toggle', () => {
        document.documentElement.style.overflow = menu.open ? 'hidden' : ''
        pageBehind.forEach((element) => {
            element.inert = menu.open
        })
    })

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            close()
            menu.querySelector('summary')?.focus()
        }
    })

    menu.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) close()
    })

    window.matchMedia(menu.dataset.desktopQuery).addEventListener('change', (event) => {
        if (event.matches) close()
    })
}
