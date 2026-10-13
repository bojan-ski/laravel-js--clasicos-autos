import { initBackToTop } from './modules/back-to-top'
import { initCookieBanner } from './modules/cookie-banner'
import { initDialogs } from './modules/dialog'
import { initFlash } from './modules/flash'
import { initGallery } from './modules/gallery'
import { initLoadingOverlay } from './modules/loading-overlay'
import { initMobileMenu } from './modules/mobile-menu'
import { initMusic } from './modules/music'

document.addEventListener('DOMContentLoaded', () => {
    initFlash()
    initMobileMenu()
    initMusic()
    initBackToTop()
    initLoadingOverlay()
    initDialogs()
    initCookieBanner()
    initGallery()

    document.querySelector('[aria-invalid="true"]')?.focus()
})
