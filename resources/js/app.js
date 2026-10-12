import { initBackToTop } from './modules/back-to-top'
import { initFlash } from './modules/flash'
import { initLoadingOverlay } from './modules/loading-overlay'
import { initMobileMenu } from './modules/mobile-menu'
import { initMusic } from './modules/music'

document.addEventListener('DOMContentLoaded', () => {
    initFlash()
    initMobileMenu()
    initMusic()
    initBackToTop()
    initLoadingOverlay()
})
