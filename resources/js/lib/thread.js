export function insertBubble(list, template, placeholder, payload, prepare = () => {}) {
    if (document.getElementById(`message-${payload.id}`)) return null

    const bubble = template.content.firstElementChild.cloneNode(true)
    const time = bubble.querySelector('[data-live-bubble-time]')

    if (placeholder) replacePlaceholder(bubble, placeholder, String(payload.id))
    bubble.id = `message-${payload.id}`
    bubble.querySelector('[data-live-bubble-text]').textContent = payload.message
    time.dateTime = payload.created_at
    time.textContent = payload.created_at_human
    prepare(bubble)

    const next = [...list.children].find((item) => messageId(item) > payload.id)
    list.insertBefore(bubble, next ?? null)
    list.hidden = false

    const empty = document.querySelector('[data-live-empty]')
    if (empty) empty.hidden = true

    return bubble
}

function messageId(item) {
    return Number(item.id.replace('message-', ''))
}

function replacePlaceholder(root, placeholder, value) {
    for (const element of [root, ...root.querySelectorAll('*')]) {
        for (const attribute of element.attributes) {
            if (attribute.value.includes(placeholder)) attribute.value = attribute.value.replaceAll(placeholder, value)
        }
    }
}
