import { afterEach, describe, expect, it } from 'vitest'
import AdvancedField from '../../../assets/js/components/AdvancedField.js'

afterEach(() => {
    document.body.innerHTML = ''
})

function draw(value = '', attributes = 'id="seo" name="seo"') {
    document.body.innerHTML = `<form><textarea data-seo-advanced ${attributes}></textarea></form>`
    const element = document.querySelector('textarea')
    element.value = value

    return element
}

function mount(element) {
    const component = new AdvancedField({ eventBus: null, logger: null, router: null })
    component.mount(element)

    return component
}

function input(name, id = 'seo') {
    return document.getElementById(`${id}-${name}`)
}

function type(name, value, event = 'input') {
    input(name).value = value
    input(name).dispatchEvent(new Event(event, { bubbles: true }))
}

describe('AdvancedField', () => {
    it('keeps its state under the attribute the host marks the field with', () => {
        const element = draw()
        new AdvancedField({ eventBus: null, logger: null, router: null }).setState(element, 'ready')

        expect(element.getAttribute('data-seo-advanced-state')).toBe('ready')
    })

    it('hides the textarea behind the inputs', () => {
        const element = draw()
        mount(element)

        expect(element.hidden).toBe(true)
    })

    it('draws a labelled input for every override', () => {
        mount(draw())

        expect([...document.querySelectorAll('.form__group label')].map((label) => label.htmlFor)).toEqual([
            'seo-title',
            'seo-description',
            'seo-image',
            'seo-image_alt',
            'seo-canonical',
            'seo-robots',
            'seo-schema',
        ])
    })

    it('fills the inputs from the stored overrides', () => {
        mount(draw('{"title":"House","robots":"noindex","schema":"Article","description":"Long"}'))

        expect([
            input('title').value,
            input('robots').value,
            input('schema').value,
            input('description').value,
            input('description').tagName,
        ]).toEqual(['House', 'noindex', 'Article', 'Long', 'TEXTAREA'])
    })

    it('hints where it helps', () => {
        mount(draw())

        expect(input('image').parentElement.querySelector('.form__description').textContent).toContain('path on the site')
        expect(input('schema').parentElement.querySelector('.form__description')).toBeNull()
    })

    it('writes what is typed back as JSON', () => {
        const element = draw()
        mount(element)
        type('title', ' House ')

        expect(element.value).toBe('{"title":"House"}')
    })

    it('writes a choice back as it changes', () => {
        const element = draw()
        mount(element)
        type('robots', 'noindex', 'change')

        expect(element.value).toBe('{"robots":"noindex"}')
    })

    it('leaves nothing stored once every input is empty', () => {
        const element = draw('{"title":"House"}')
        mount(element)
        type('title', '')

        expect(element.value).toBe('')
    })

    it('keeps a stored value the lists do not offer', () => {
        const element = draw('{"robots":"noarchive"}')
        mount(element)

        expect([input('robots').value, input('robots').selectedOptions[0].textContent]).toEqual([
            'noarchive',
            'noarchive',
        ])
    })

    it('keeps stored keys it does not draw', () => {
        const element = draw('{"other":"x"}')
        mount(element)
        type('title', 'House')

        expect(JSON.parse(element.value)).toEqual({ other: 'x', title: 'House' })
    })

    it('ignores a stored override that is not text', () => {
        mount(draw('{"title":3}'))

        expect(input('title').value).toBe('')
    })

    it.each([
        ['not JSON', '{not json'],
        ['a list', '["title"]'],
        ['nothing but null', 'null'],
    ])('leaves the textarea showing when it holds %s, so nothing is overwritten', (_label, value) => {
        const element = draw(value)
        mount(element)

        expect([element.hidden, element.value, document.querySelector('.form__group')]).toEqual([false, value, null])
    })

    it('names its inputs after the field when it has no id', () => {
        mount(draw('', 'name="overrides"'))

        expect(input('title', 'overrides')).not.toBeNull()
    })

    it('puts the textarea back when it is unmounted', () => {
        const element = draw()
        const component = mount(element)
        component.unmount(element)

        expect([element.hidden, document.querySelector('.form__group')]).toEqual([false, null])
    })
})
