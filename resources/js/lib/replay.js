export function replay(element, attribute) {
    element.removeAttribute(attribute)
    void element.offsetWidth
    element.setAttribute(attribute, '')
}
