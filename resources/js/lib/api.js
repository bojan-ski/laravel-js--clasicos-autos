function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

export async function api(path, options = {}) {
    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    })

    if (!response.ok) {
        throw new Error(`${response.status}: ${await response.text()}`)
    }

    return response.status === 204 ? null : response.json()
}
