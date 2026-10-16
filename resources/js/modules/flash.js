import { dismissible } from '../lib/flash'

export function initFlash() {
    const flash = document.querySelector('[data-flash]')
    if (!flash) return

    dismissible(flash)
}
