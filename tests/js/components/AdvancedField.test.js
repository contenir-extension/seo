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

    describe('in the form the host draws', () => {
        function drawHosted(error = '') {
            document.body.innerHTML = `<div class="form__row-group">
                <div class="form__row" id="before"></div>
                <div class="form__row" id="host"><div class="form__col"><div class="form__field">
                    <label for="seo" class="form__label">Overrides</label>
                    <textarea data-seo-advanced id="seo" name="seo"></textarea>
                    ${error}
                </div></div></div>
            </div>`

            return document.getElementById('seo')
        }

        it('draws a row of its own for each input, beside the other fields', () => {
            mount(drawHosted())

            expect([...document.querySelector('.form__row-group').children].map((row) => row.id || 'input')).toEqual([
                'before',
                'input',
                'textarea',
                'input',
                'input',
                'input',
                'host',
            ])
        })

        it('sits the search engines and schema type side by side', () => {
            mount(drawHosted())

            expect(input('robots').closest('.form__row')).toBe(input('schema').closest('.form__row'))
            expect(input('robots').closest('.form__col')).not.toBe(input('schema').closest('.form__col'))
        })

        it('hides the field it stands in for, heading and all', () => {
            mount(drawHosted())

            expect(document.getElementById('host').hidden).toBe(true)
        })

        it('keeps showing an error on the field, without its heading', () => {
            mount(drawHosted('<p class="form__error">Bad address</p>'))

            expect([document.getElementById('host').hidden, document.querySelector('label[for="seo"]').hidden]).toEqual([
                false,
                true,
            ])
        })

        it('puts the field back when it is unmounted', () => {
            const element = drawHosted()
            mount(element).unmount(element)

            expect([
                document.getElementById('host').hidden,
                document.querySelector('label[for="seo"]').hidden,
                document.querySelector('.form__row-group').children.length,
            ]).toEqual([false, false, 2])
        })
    })

    it('hides the textarea behind the inputs', () => {
        const element = draw()
        mount(element)

        expect(element.hidden).toBe(true)
    })

    it('draws a labelled input for every override', () => {
        mount(draw())

        expect([...document.querySelectorAll('.form__field label')].map((label) => label.htmlFor)).toEqual([
            'seo-title',
            'seo-description',
            'seo-image_alt',
            'seo-canonical',
            'seo-robots',
            'seo-schema',
        ])
    })

    it('fills the inputs from the stored overrides', () => {
        mount(draw('{"image_alt":"A house","robots":"noindex","schema":"Article"}'))

        expect([input('image_alt').value, input('robots').value, input('schema').value]).toEqual([
            'A house',
            'noindex',
            'Article',
        ])
    })

    it('draws each input as the dashboard draws its own', () => {
        mount(draw())

        expect([
            input('image_alt').className,
            input('image_alt').type,
            input('robots').className,
            input('image_alt').closest('.form__row > .form__col > .form__field') !== null,
        ]).toEqual(['input', 'text', 'select', true])
    })

    it('hints where it helps, and ties the hint to its input', () => {
        mount(draw())

        expect(document.getElementById(input('canonical').getAttribute('aria-describedby')).textContent).toContain(
            'published elsewhere',
        )
        expect(input('schema').parentElement.querySelector('.form__description')).toBeNull()
    })

    it('draws the meta description as a textarea', () => {
        mount(draw('{"title":"House","description":"A house."}'))

        expect([
            input('title').value,
            input('title').tagName,
            input('description').value,
            input('description').tagName,
            input('description').rows,
            input('description').className,
        ]).toEqual(['House', 'INPUT', 'A house.', 'TEXTAREA', 3, 'textarea'])
    })

    it('writes what is typed back as JSON', () => {
        const element = draw()
        mount(element)
        type('image_alt', ' A house ')

        expect(element.value).toBe('{"image_alt":"A house"}')
    })

    it('writes a choice back as it changes', () => {
        const element = draw()
        mount(element)
        type('robots', 'noindex', 'change')

        expect(element.value).toBe('{"robots":"noindex"}')
    })

    it('leaves nothing stored once every input is empty', () => {
        const element = draw('{"image_alt":"A house"}')
        mount(element)
        type('image_alt', '')

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
        type('image_alt', 'A house')

        expect(JSON.parse(element.value)).toEqual({ other: 'x', image_alt: 'A house' })
    })

    it('ignores a stored override that is not text', () => {
        mount(draw('{"image_alt":3}'))

        expect(input('image_alt').value).toBe('')
    })

    it.each([
        ['not JSON', '{not json'],
        ['a list', '["image_alt"]'],
        ['nothing but null', 'null'],
    ])('leaves the textarea showing when it holds %s, so nothing is overwritten', (_label, value) => {
        const element = draw(value)
        mount(element)

        expect([element.hidden, element.value, document.querySelector('.form__field')]).toEqual([false, value, null])
    })

    it('names its inputs after the field when it has no id', () => {
        mount(draw('', 'name="overrides"'))

        expect(input('image_alt', 'overrides')).not.toBeNull()
    })

    it('puts the textarea back when it is unmounted', () => {
        const element = draw()
        const component = mount(element)
        component.unmount(element)

        expect([element.hidden, document.querySelector('.form__field')]).toEqual([false, null])
    })
})
