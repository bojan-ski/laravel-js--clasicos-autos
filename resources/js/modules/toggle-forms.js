import { api } from '../lib/api'
import { showFlash } from '../lib/flash'

const STATE_KEYS = { bookmark: 'bookmarked', compare: 'selected' }

export function initToggleForms() {
    document.querySelectorAll('form[data-toggle-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault()
            if (form.getAttribute('aria-busy') === 'true') return

            toggle(form)
        })
    })
}

async function toggle(form) {
    const kind = form.dataset.toggleForm
    const pressed = form.querySelector('[aria-pressed]')?.getAttribute('aria-pressed') === 'true'
    form.setAttribute('aria-busy', 'true')

    let payload
    try {
        payload = await api(form.action, { method: pressed ? 'DELETE' : 'POST' })
    } catch (error) {
        form.removeAttribute('aria-busy')

        if (error.status === 409 && error.body?.message) {
            showFlash('error', error.body.message)
        } else {
            form.submit()
        }
        return
    }

    form.removeAttribute('aria-busy')

    const action = form.getAttribute('action')
    document.querySelectorAll(`form[data-toggle-form="${kind}"]`).forEach((twin) => {
        if (twin.getAttribute('action') === action) setPressed(twin, payload[STATE_KEYS[kind]])
    })

    if (typeof payload.count === 'number') updateSelectedCount(payload.count)

    showFlash('success', payload.message)
}

function setPressed(form, pressed) {
    const button = form.querySelector('[aria-pressed]')
    button?.setAttribute('aria-pressed', String(pressed))
    button?.setAttribute('data-toggled', '')

    const method = form.querySelector('input[name="_method"]')
    if (pressed && !method) {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = '_method'
        input.value = 'DELETE'
        form.prepend(input)
    } else if (!pressed && method) {
        method.remove()
    }
}

function updateSelectedCount(count) {
    document.querySelectorAll('[data-selected-count]').forEach((element) => {
        element.textContent = element.dataset.template.replace(':count', String(count))
        element.hidden = count === 0
    })
}
