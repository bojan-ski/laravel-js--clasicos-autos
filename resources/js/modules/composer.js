import { api } from '../lib/api'
import { insertBubble } from '../lib/thread'

const NOT_STORED = [401, 403, 404, 419]

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
            if (submit.getAttribute('aria-disabled') === 'true' || form.getAttribute('aria-busy') === 'true') {
                event.preventDefault()
                return
            }

            if (form.hasAttribute('data-composer-async') && document.querySelector('header[data-live-connected]')) {
                event.preventDefault()
                send(form, input, counter, update)
            }
        })

        input.addEventListener('input', () => {
            if (input.getAttribute('aria-invalid') === 'true') clearError(form, input, counter)
            update()
        })
        update()
    })
}

async function send(form, input, counter, update) {
    const text = input.value
    form.setAttribute('aria-busy', 'true')

    let response
    try {
        response = await api(form.action, { method: 'POST', body: JSON.stringify({ message: text }) })
    } catch (error) {
        const errors = error.status === 422 ? error.body?.errors?.message : null

        if (errors?.length) {
            form.removeAttribute('aria-busy')
            showError(form, input, counter, errors[0])
        } else if (NOT_STORED.includes(error.status)) {
            form.submit()
        } else {
            window.location.reload()
        }
        return
    }

    form.removeAttribute('aria-busy')
    appendOwn(response.data)
    if (input.value === text) input.value = ''
    clearError(form, input, counter)
    update()
}

function appendOwn(payload) {
    const thread = document.querySelector(`[data-live-thread="${payload.conversation_id}"]`)
    const list = thread?.querySelector('ol')
    const template = thread?.querySelector('template[data-own-bubble]')
    if (!list || !template) return

    const bubble = insertBubble(list, template, template.dataset.ownBubblePlaceholder, payload, (clone) => {
        const context = clone.querySelector('[data-delete-context]')
        if (context) context.textContent = context.dataset.template.replace(':time', payload.created_at_human)
    })

    if (!bubble) return

    thread.scrollIntoView({ block: 'nearest' })
    thread.scrollTop = thread.scrollHeight
}

function showError(form, input, counter, message) {
    const error = form.querySelector('[data-composer-error]')
    if (!error) return

    error.textContent = ''
    requestAnimationFrame(() => {
        error.textContent = message
    })
    input.setAttribute('aria-invalid', 'true')
    input.setAttribute('aria-describedby', `${counter.id} ${error.id}`)
    input.focus()
}

function clearError(form, input, counter) {
    const error = form.querySelector('[data-composer-error]')
    if (error) error.textContent = ''

    input.removeAttribute('aria-invalid')
    input.setAttribute('aria-describedby', counter.id)
}
