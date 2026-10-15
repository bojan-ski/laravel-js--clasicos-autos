export function initComposer() {
    document.querySelectorAll('[data-composer]').forEach((form) => {
        const input = form.querySelector('[data-composer-input]')
        const counter = form.querySelector('[data-composer-counter]')
        const submit = form.querySelector('[data-composer-submit]')
        if (!input || !counter || !submit) return

        const min = Number(counter.dataset.min)
        const max = Number(counter.dataset.max)

        const update = () => {
            const length = [...input.value.trim()].length

            counter.textContent = counter.dataset.template.replace(':count', String(length))
            submit.setAttribute('aria-disabled', String(length < min || length > max))
        }

        form.addEventListener('submit', (event) => {
            if (submit.getAttribute('aria-disabled') === 'true') event.preventDefault()
        })

        input.addEventListener('input', update)
        update()
    })
}
