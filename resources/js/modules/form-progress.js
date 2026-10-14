export function initFormProgress() {
    const bar = document.querySelector('[data-form-progress]')
    const sections = [...document.querySelectorAll('[data-form-section]')]
    if (!bar || sections.length === 0) return

    const segments = [...bar.querySelectorAll('[data-form-progress-segment]')]

    const update = () => {
        const reached = sections.filter((section) => section.getBoundingClientRect().top < window.innerHeight).length
        segments.forEach((segment, index) => segment.toggleAttribute('data-filled', index < reached))
    }

    document.fonts.ready.then(() => requestAnimationFrame(() => {
        const observer = new IntersectionObserver(update)
        sections.forEach((section) => observer.observe(section))
        update()
    }))
}
