export function initModelSelect() {
    document.querySelectorAll('[data-model-select]').forEach(setUpModelSelect)
}

function setUpModelSelect(root) {
    const maker = root.querySelector('[data-model-select-maker]')
    const model = root.querySelector('[data-model-select-model]')
    const other = root.querySelector('[data-model-select-other]')
    const otherInput = other?.querySelector('input')
    const configElement = root.querySelector('[data-model-select-config]')
    if (!maker || !model || !other || !otherInput || !configElement) return

    const config = JSON.parse(configElement.textContent)
    const placeholder = model.querySelector('option[value=""]')?.cloneNode(true)
    const otherOption = model.querySelector(`option[value="${config.other}"]`)?.cloneNode(true)
    if (!placeholder || !otherOption) return

    const toggleOther = () => {
        const isOther = model.value === config.other
        other.hidden = !isOther
        otherInput.required = isOther
    }

    const rebuild = () => {
        const selected = model.value
        const options = [placeholder.cloneNode(true)]

        if (maker.value !== '') {
            for (const { id, name } of config.models[maker.value] ?? []) {
                options.push(new Option(name, String(id)))
            }
            options.push(otherOption.cloneNode(true))
        }

        model.replaceChildren(...options)
        model.disabled = maker.value === ''
        model.value = options.some((option) => option.value === selected) ? selected : ''
        toggleOther()
    }

    maker.addEventListener('change', rebuild)
    model.addEventListener('change', toggleOther)
    rebuild()
}
