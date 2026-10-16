const openers = new WeakMap()

export function initDialogs() {
    const supportsCommands = 'command' in HTMLButtonElement.prototype

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-dialog-open]')
        if (opener) {
            const dialog = document.getElementById(opener.dataset.dialogOpen)
            if (!(dialog instanceof HTMLDialogElement)) return

            openers.set(dialog, opener)
            if (!supportsCommands && !dialog.open) dialog.showModal()
            return
        }

        const closer = event.target.closest('[data-dialog-close]')
        if (closer && !supportsCommands) closer.closest('dialog')?.close()
    })

    document.addEventListener('submit', (event) => {
        const dialog = event.target.closest('dialog')
        if (dialog?.open) dialog.close()
    })

    document.addEventListener('close', (event) => {
        if (!(event.target instanceof HTMLDialogElement)) return

        const opener = openers.get(event.target)
        if (opener?.isConnected && document.activeElement !== opener) opener.focus()
    }, true)

    const autoOpen = document.querySelector('dialog[data-dialog-autoopen]')
    if (autoOpen instanceof HTMLDialogElement && !autoOpen.open) {
        const opener = document.querySelector(`[data-dialog-open="${CSS.escape(autoOpen.id)}"]`)
        if (opener) openers.set(autoOpen, opener)
        autoOpen.showModal()
    }
}
