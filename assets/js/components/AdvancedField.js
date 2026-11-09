import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'

/**
 * AdvancedField — draws a record's SEO overrides as a group of inputs.
 *
 * Registered against `[data-seo-advanced]`, the textarea the host draws for a
 * `seo:advanced` field, which holds the overrides as JSON. Each override gets a
 * row of its own beside the form's other fields, in the host's own markup. The
 * textarea stays in the form, hidden, so the JSON is still what is posted and
 * what the add-on validates: each input writes it back as it changes. Its row
 * stays only to show an error the host put there. A value the lists don't offer,
 * or a textarea that doesn't hold an object, is kept rather than lost, so an
 * editor never overwrites what they can't see.
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

/** Each row of the form, with the fields that sit side by side in it. */
const ROWS = [
    [{ name: 'title', label: 'Meta Title' }],
    [{ name: 'description', label: 'Meta Description', multiline: true }],
    [{ name: 'image_alt', label: 'Share image description' }],
    [{ name: 'canonical', label: 'Canonical address', hint: 'Only when this page copies one published elsewhere.' }],
    [
        { name: 'robots', label: 'Search engines', options: ROBOTS },
        { name: 'schema', label: 'Schema type', options: SCHEMAS },
    ],
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
        select.className = 'select'
        return select
    }

    if (field.multiline) {
        const textarea = document.createElement('textarea')
        textarea.rows = 1
        textarea.className = 'textarea textarea--grow'
        textarea.setAttribute('data-autogrow', '')
        textarea.value = value
        return textarea
    }

    const input = document.createElement('input')
    input.type = 'text'
    input.className = 'input'
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

        const inputs = new Map()
        const column = (field) => {
            const id = `${element.id || element.name}-${field.name}`
            const label = document.createElement('label')
            label.className = 'form__label'
            label.htmlFor = id
            label.textContent = field.label

            const input = control(field, typeof values[field.name] === 'string' ? values[field.name] : '')
            input.id = id
            inputs.set(field.name, input)

            const wrapper = document.createElement('div')
            wrapper.className = 'form__field'
            wrapper.append(label, input)
            if (field.hint) {
                const hint = document.createElement('p')
                hint.className = 'form__description'
                hint.id = `${id}-desc`
                hint.textContent = field.hint
                input.setAttribute('aria-describedby', hint.id)
                wrapper.append(hint)
            }

            const col = document.createElement('div')
            col.className = 'form__col'
            col.append(wrapper)

            return col
        }
        const rows = ROWS.map((fields) => {
            const row = document.createElement('div')
            row.className = 'form__row'
            row.append(...fields.map(column))

            return row
        })

        const write = () => {
            const written = { ...values }
            for (const [name, input] of inputs) {
                delete written[name]
                if (input.value.trim() !== '') written[name] = input.value.trim()
            }
            element.value = Object.keys(written).length === 0 ? '' : JSON.stringify(written)
        }

        const field = element.closest('.form__field')
        const label = field?.querySelector('label') ?? null
        const row = element.closest('.form__row')
        const shown = row ?? element
        shown.before(...rows)
        for (const row of rows) {
            row.addEventListener('input', write)
            row.addEventListener('change', write)
        }

        element.hidden = true
        if (label) label.hidden = true
        if (row && !field.querySelector('.form__error')) row.hidden = true

        return {
            cleanup: () => {
                for (const row of rows) row.remove()
                element.hidden = false
                if (label) label.hidden = false
                if (row) row.hidden = false
            },
        }
    }
}
