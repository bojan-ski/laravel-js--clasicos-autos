export function initBrandChips() {
    const rows = document.querySelectorAll('[data-brand-chips]')
    if (rows.length === 0) return

    document.fonts.ready.then(() => requestAnimationFrame(() => rows.forEach(centerCurrentChip)))
}

function centerCurrentChip(row) {
    const current = row.querySelector('[aria-current="true"]')
    if (!current || row.scrollWidth <= row.clientWidth) return

    const rowBox = row.getBoundingClientRect()
    const chipBox = current.getBoundingClientRect()
    row.scrollLeft += chipBox.left - rowBox.left - (rowBox.width - chipBox.width) / 2
}
