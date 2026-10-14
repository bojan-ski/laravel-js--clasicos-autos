export function initErrorFocus() {
    const summary = document.querySelector('[data-error-summary]')
    const firstVisibleInvalid = [...document.querySelectorAll('[aria-invalid="true"]')].find((field) => field.offsetParent !== null)

    const target = summary ?? firstVisibleInvalid

    target?.focus()
}
