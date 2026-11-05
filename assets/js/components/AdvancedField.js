import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'

/**
 * AdvancedField — draws a record's SEO overrides as a group of inputs.
 *
 * Registered against `[data-seo-advanced]`, the textarea the host draws for a
 * `seo:advanced` field, which holds the overrides as JSON. The textarea stays
 * in the form, hidden, so the JSON is still what is posted and what the add-on
 * validates: each input writes it back as it changes. A value the lists don't
 * offer, or a textarea that doesn't hold an object, is kept rather than lost,
 * so an editor never overwrites what they can't see.
 */
const ROBOTS = [
    ['', 'As the site sets it'],
    ['noindex', 'Keep out of search results'],
    ['nofollow', "Don't follow its links"],
    ['noindex, nofollow', "Keep out of search results and don't follow its links"],
]

const SCHEMAS = [
    ['', "The section's type"],
    ['WebPage', 'Web page'],
    ['AboutPage', 'About page'],
    ['ContactPage', 'Contact page'],
    ['CollectionPage', 'Collection page'],
    ['CreativeWork', 'Creative work'],
    ['Article', 'Article'],
    ['NewsArticle', 'News article'],
    ['BlogPosting', 'Blog post'],
]

const FIELDS = [
    { name: 'title', label: 'Share title', hint: 'Replaces the title on this page and in shared links.' },
    { name: 'description', label: 'Share description', multiline: true },
    { name: 'image', label: 'Share image', hint: 'A full address, or a path on the site starting with /.' },
    { name: 'image_alt', label: 'Share image description' },
    { name: 'canonical', label: 'Canonical address', hint: 'Only when this page copies one published elsewhere.' },
    { name: 'robots', label: 'Search engines', options: ROBOTS },
    { name: 'schema', label: 'Schema type', options: SCHEMAS },
]

function parse(text) {
    try {
        const value = JSON.parse(text)
        return value !== null && typeof value === 'object' && !Array.isArray(value) ? value : null
    } catch {
        return null
    }
}

function control(field, value) {
    if (field.options) {
        const select = document.createElement('select')
        const options = field.options.some(([option]) => option === value)
            ? field.options
            : [...field.options, [value, value]]
        for (const [option, label] of options) {
            select.add(new Option(label, option, false, option === value))
        }
        select.className = 'form__control form__control--select'
        return select
    }

    const input = document.createElement(field.multiline ? 'textarea' : 'input')
    input.className = 'form__control'
    input.value = value
    return input
}

export default class AdvancedField extends BaseComponent {
    _getSelector() {
        return 'data-seo-advanced'
    }

    _init(element) {
        const text = element.value.trim()
        const values = text === '' ? {} : parse(text)
        if (values === null) {
            return {}
        }

        const group = document.createElement('div')
        group.className = 'stack stack--md'
        const inputs = new Map()
        for (const field of FIELDS) {
            const id = `${element.id || element.name}-${field.name}`
            const wrapper = document.createElement('div')
            wrapper.className = 'form__group'

            const label = document.createElement('label')
            label.className = 'form__label'
            label.htmlFor = id
            label.textContent = field.label
            wrapper.append(label)

            const input = control(field, typeof values[field.name] === 'string' ? values[field.name] : '')
            input.id = id
            inputs.set(field.name, input)
            wrapper.append(input)

            if (field.hint) {
                const hint = document.createElement('p')
                hint.className = 'form__description'
                hint.textContent = field.hint
                wrapper.append(hint)
            }

            group.append(wrapper)
        }

        const write = () => {
            const written = { ...values }
            for (const [name, input] of inputs) {
                delete written[name]
                if (input.value.trim() !== '') written[name] = input.value.trim()
            }
            element.value = Object.keys(written).length === 0 ? '' : JSON.stringify(written)
        }

        group.addEventListener('input', write)
        group.addEventListener('change', write)
        element.hidden = true
        element.after(group)

        return {
            cleanup: () => {
                group.remove()
                element.hidden = false
            },
        }
    }
}
