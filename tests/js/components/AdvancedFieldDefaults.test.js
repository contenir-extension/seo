import { afterEach, describe, expect, it } from 'vitest'
import AdvancedField from '../../../assets/js/components/AdvancedField.js'

afterEach(() => {
    document.body.innerHTML = ''
})

const SHARE = { url: 'https://www.example.com/share.png', alt: 'Word—Form' }

function entry(value, from, column = null) {
    return { value, from, column }
}

const DEFAULTS = {
    title: entry('Jo Roca', 'column', 'title'),
    description: entry('Power is a word.', 'content'),
    image: entry('https://www.example.com/asset/library/jr.jpg', 'column', 'thumbnail'),
    image_alt: entry('Jo Roca', 'column', 'title'),
    canonical: entry(null, 'page'),
    robots: entry('index, follow', 'site'),
    schema: entry('Article', 'section'),
}

/**
 * The host's record form: a Title field the defaults name, and the overrides' textarea with the data the host
 * gave it.
 */
function draw(value = '', data = { defaults: DEFAULTS, siteImage: SHARE }, form = true) {
    const fields = `<div class="form__field"><label class="form__label" for="title">Name</label>
            <input name="title" id="title"></div>
        <div class="form__field"><input name="thumbnail[]"></div>
        <textarea data-seo-advanced id="seo" name="seo"></textarea>`
    document.body.innerHTML = form ? `<form>${fields}</form>` : fields
    const element = document.querySelector('textarea')
    element.setAttribute('data-control', typeof data === 'string' ? data : JSON.stringify(data))
    element.value = value

    return element
}

function mount(element) {
    const component = new AdvancedField({ eventBus: null, logger: null, router: null })
    component.mount(element)

    return component
}

function input(name) {
    return document.getElementById(`seo-${name}`)
}

function toggle(name) {
    return document.querySelector(`[data-seo-override="${name}"]`)
}

function flip(name) {
    toggle(name).checked = !toggle(name).checked
    toggle(name).dispatchEvent(new Event('change', { bubbles: true }))
}

function type(name, value, event = 'input') {
    input(name).value = value
    input(name).dispatchEvent(new Event(event, { bubbles: true }))
}

function note(name) {
    return document.getElementById(`seo-${name}-source`).textContent
}

function preview() {
    return input('image').parentElement.querySelector('img')
}

describe('AdvancedField with the defaults the host gives it', () => {
    it('draws an Override switch for each field, the share image among them', () => {
        mount(draw())

        expect([...document.querySelectorAll('[data-seo-override]')].map((box) => box.getAttribute('aria-label'))).toEqual([
            'Override Meta Title',
            'Override Meta Description',
            'Override Share image',
            'Override Share image description',
            'Override Canonical address',
            'Override Search engines',
            'Override Schema type',
        ])
    })

    it('draws each switch in the dashboard’s inherit markup, without the attributes its own toggle acts on', () => {
        mount(draw())
        const shell = toggle('title').closest('.inherit')

        expect([
            shell.querySelector('.inherit__toggle .inherit__icon--inherited svg') !== null,
            shell.querySelector('.inherit__toggle .inherit__icon--overridden svg') !== null,
            shell.querySelector('.inherit__control #seo-title') !== null,
            shell.closest('.form__row').classList.contains('seo-advanced'),
            shell.hasAttribute('data-inherit'),
            toggle('title').hasAttribute('name'),
        ]).toEqual([true, true, true, true, false, false])
    })

    it('shows the default, read-only, where nothing is overridden', () => {
        mount(draw())

        expect([toggle('title').checked, input('title').disabled, input('title').value, note('title')]).toEqual([
            false,
            true,
            'Jo Roca',
            'From the Name, as last saved.',
        ])
    })

    it.each([
        ['description', 'From its content, as last saved.'],
        ['canonical', "This page's own address, as last saved."],
        ['robots', 'As the site sets it, as last saved.'],
        ['schema', "The section's type, as last saved."],
    ])('says where the %s comes from', (name, said) => {
        mount(draw())

        expect(note(name)).toBe(said)
    })

    it('names a field the form gives no label by its column', () => {
        mount(draw())

        expect(note('image')).toBe('From the thumbnail, as last saved.')
    })

    it('names a field outside any form by its column', () => {
        mount(draw('', { defaults: { ...DEFAULTS, image_alt: entry('x', 'column', 'image_caption') } }, false))

        expect(note('image_alt')).toBe('From the image caption, as last saved.')
    })

    it.each([
        ['the site', entry('Every word.', 'site'), "The site's description, as last saved."],
        ['the site, for a field it names no source for', entry('x', 'site'), "The site's setting, as last saved."],
        ['nothing', entry(null, 'none'), 'Nothing yet, as last saved.'],
        ['a source it does not know', entry('x', 'elsewhere'), 'Nothing yet, as last saved.'],
    ])('says where a default from %s comes from', (_label, given, said) => {
        const name = said.startsWith("The site's setting") ? 'title' : 'description'
        mount(draw('', { defaults: { ...DEFAULTS, [name]: given } }))

        expect(note(name)).toBe(said)
    })

    it('treats a field the host gave no default for as having none', () => {
        mount(draw('', { defaults: {} }))

        expect([input('title').value, note('title')]).toEqual(['', 'Nothing yet, as last saved.'])
    })

    it('starts a field with what the record overrides, editable', () => {
        mount(draw('{"title":"Jo Roca on power"}'))

        expect([
            toggle('title').checked,
            input('title').disabled,
            input('title').value,
            note('title'),
            toggle('title').closest('.inherit').className,
        ]).toEqual([true, false, 'Jo Roca on power', 'Your own. Switch off to go back to the default.', 'inherit inherit--overridden'])
    })

    it('starts an override from the default and saves it', () => {
        const element = draw()
        mount(element)
        flip('title')

        expect([input('title').disabled, input('title').value, element.value]).toEqual([false, 'Jo Roca', '{"title":"Jo Roca"}'])
    })

    it('leaves a field out of the save once it is switched off, and restores it when switched back on', () => {
        const element = draw('{"title":"Jo Roca on power","robots":"noindex"}')
        mount(element)
        type('title', 'Jo Roca, on power')
        flip('title')
        const off = element.value
        flip('title')

        expect([off, input('title').value, JSON.parse(element.value)]).toEqual([
            '{"robots":"noindex"}',
            'Jo Roca, on power',
            { robots: 'noindex', title: 'Jo Roca, on power' },
        ])
    })

    it('draws the search engines without an "as the site sets it" choice, which the switch now is, but with the rule the site sets', () => {
        mount(draw('{"robots":"noindex"}'))

        expect([...input('robots').options].map((option) => option.value)).toEqual([
            'noindex',
            'nofollow',
            'noindex, nofollow',
            'index, follow',
        ])
    })

    it('shows a default the list does not offer, in plain words', () => {
        mount(draw())

        expect([input('robots').value, input('robots').selectedOptions[0].textContent]).toEqual([
            'index, follow',
            'Show in search results and follow its links',
        ])
    })

    it('shows a default the list does not offer and has no words for as it is', () => {
        mount(draw('', { defaults: { ...DEFAULTS, schema: entry('Recipe', 'section') } }))

        expect(input('schema').selectedOptions[0].textContent).toBe('Recipe')
    })

    it('ties the canonical address to both its note and its hint', () => {
        mount(draw())

        expect(input('canonical').getAttribute('aria-describedby')).toBe('seo-canonical-source seo-canonical-desc')
    })

    describe('the share image', () => {
        it('shows the page’s own image while it is not overridden', () => {
            mount(draw())

            expect([input('image').value, input('image').disabled, preview().getAttribute('src'), preview().hidden]).toEqual([
                '',
                true,
                DEFAULTS.image.value,
                false,
            ])
        })

        it("offers the site's share image, and shows it once chosen", () => {
            const element = draw()
            mount(element)
            flip('image')

            expect([input('image').value, preview().getAttribute('src'), element.value]).toEqual([
                '@site',
                SHARE.url,
                '{"image":"@site"}',
            ])
        })

        it('keeps an image the record already holds as a choice, and shows it', () => {
            mount(draw('{"image":"/asset/seo/house.jpg"}'))

            expect([
                [...input('image').options].map((option) => [option.value, option.textContent]),
                input('image').value,
                preview().getAttribute('src'),
            ]).toEqual([
                [
                    ['', "This page's image"],
                    ['@site', "The site's share image"],
                    ['/asset/seo/house.jpg', 'This image: house.jpg'],
                ],
                '/asset/seo/house.jpg',
                '/asset/seo/house.jpg',
            ])
        })

        it('shows the image chosen as the choice changes', () => {
            mount(draw('{"image":"/asset/seo/house.jpg"}'))
            type('image', '@site', 'change')

            expect(preview().getAttribute('src')).toBe(SHARE.url)
        })

        it('can’t be overridden while there is nothing to choose instead', () => {
            mount(draw('', { defaults: DEFAULTS }))

            expect(toggle('image').disabled).toBe(true)
        })

        it("hides the preview of the site's image where the site has none", () => {
            mount(draw('{"image":"@site"}', { defaults: DEFAULTS }))

            expect([input('image').value, preview().hidden]).toEqual(['@site', true])
        })

        it('hides a preview that fails to load', () => {
            mount(draw())
            preview().dispatchEvent(new Event('error'))

            expect(preview().hidden).toBe(true)
        })

        it('hides the preview where the page has no image', () => {
            mount(draw('', { defaults: { ...DEFAULTS, image: entry(null, 'none') }, siteImage: SHARE }))

            expect([note('image'), preview().hidden]).toEqual(['Nothing yet, as last saved.', true])
        })
    })

    it.each([
        ['not JSON', '{not json'],
        ['no defaults', '{"siteImage":null}'],
        ['defaults that are a list', '{"defaults":[]}'],
        ['defaults that are not an object', '{"defaults":"x"}'],
    ])('draws plain inputs, as before, where the data has %s', (_label, data) => {
        mount(draw('', data))

        expect([document.querySelector('[data-seo-override]'), input('image')]).toEqual([null, null])
    })
})
