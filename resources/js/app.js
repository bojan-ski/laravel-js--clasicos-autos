import { initBackToTop } from './modules/back-to-top'
import { initBrandChips } from './modules/brand-chips'
import { initCookieBanner } from './modules/cookie-banner'
import { initDialogs } from './modules/dialog'
import { initFlash } from './modules/flash'
import { initFormProgress } from './modules/form-progress'
import { initGallery } from './modules/gallery'
import { initLoadingOverlay } from './modules/loading-overlay'
import { initMobileMenu } from './modules/mobile-menu'
import { initModelSelect } from './modules/model-select'
import { initMusic } from './modules/music'
import { initPhotoInput } from './modules/photo-input'

document.addEventListener('DOMContentLoaded', () => {
    initFlash()
    initMobileMenu()
    initMusic()
    initBackToTop()
    initLoadingOverlay()
    initDialogs()
    initCookieBanner()
    initGallery()
    initBrandChips()
    initModelSelect()
    initPhotoInput()
    initFormProgress()

    document.querySelector('[aria-invalid="true"]')?.focus()
})
