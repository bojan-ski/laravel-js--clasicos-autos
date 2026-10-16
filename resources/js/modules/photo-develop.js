export function initPhotoDevelop() {
    document.querySelectorAll('img[data-develop]').forEach((image) => {
        if (image.complete) return

        const developed = () => image.removeAttribute('data-developing')
        image.setAttribute('data-developing', '')
        image.addEventListener('load', developed, { once: true })
        image.addEventListener('error', developed, { once: true })
    })
}
