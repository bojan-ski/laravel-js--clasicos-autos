import { initBackToTop } from './modules/back-to-top'
import { initBrandChips } from './modules/brand-chips'
import { initComposer } from './modules/composer'
import { initCookieBanner } from './modules/cookie-banner'
import { initDialogs } from './modules/dialog'
import { initErrorFocus } from './modules/error-focus'
import { initFlash } from './modules/flash'
import { initFormProgress } from './modules/form-progress'
import { initGallery } from './modules/gallery'
import { initLiveMessages } from './modules/live-messages'
import { initLoadingOverlay } from './modules/loading-overlay'
import { initMobileMenu } from './modules/mobile-menu'
import { initModelSelect } from './modules/model-select'
import { initMusic } from './modules/music'
import { initPhotoInput } from './modules/photo-input'
import { initToggleForms } from './modules/toggle-forms'

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
    initComposer()
    initToggleForms()
    initErrorFocus()
    initLiveMessages()
})
