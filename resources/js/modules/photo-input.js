export function initPhotoInput() {
    const roots = document.querySelectorAll('[data-photo-input]')
    if (roots.length === 0) return

    roots.forEach((root) => {
        root.addEventListener('change', (event) => {
            if (event.target.matches('input[type="file"]')) updateStatus(root)
        })

        const drop = root.querySelector('[data-photo-drop]')
        if (drop) setUpDropZone(drop)
    })
}

function setUpDropZone(drop) {
    const input = drop.querySelector('input[type="file"]')
    if (!input) return

    const highlight = (event) => {
        event.preventDefault()
        drop.dataset.dragging = ''
    }

    drop.addEventListener('dragenter', highlight)
    drop.addEventListener('dragover', highlight)
    drop.addEventListener('dragleave', (event) => {
        if (!drop.contains(event.relatedTarget)) delete drop.dataset.dragging
    })
    drop.addEventListener('drop', (event) => {
        event.preventDefault()
        delete drop.dataset.dragging
        if (!event.dataTransfer?.files.length) return

        input.files = event.dataTransfer.files
        input.dispatchEvent(new Event('change', { bubbles: true }))
    })
}

function updateStatus(root) {
    const status = root.querySelector('[data-photo-status]')
    if (!status) return

    const total = [...root.querySelectorAll('input[type="file"]')].reduce((sum, input) => sum + (input.files?.length ?? 0), 0)
    const template = total === 1 ? root.dataset.countOne : root.dataset.countOther
    status.textContent = total === 0 ? '' : template.replace(':count', String(total))
}
