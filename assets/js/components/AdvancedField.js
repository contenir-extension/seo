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
 *
 * Where the host gives the control what the page uses for each field when it
 * isn't overridden (`data-control`, from the add-on's ControlDefaults), each
 * field is drawn as the dashboard draws a setting it inherits: an Override
 * switch, the default shown read-only while it is off, with where it comes
 * from, and the field editable while it is on. The share image gets a field of
 * its own. Switching off keeps what was typed while the form is open, so
 * switching back on restores it, but the save leaves it out. Without the data,
 * every field is a plain input, as before.
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

const CANONICAL_HINT = 'Only when this page copies one published elsewhere.'

/** Each row of the form, with the fields that sit side by side in it. */
const ROWS = [
    [{ name: 'title', label: 'Meta Title' }],
    [{ name: 'description', label: 'Meta Description', multiline: true }],
    [{ name: 'image_alt', label: 'Share image description' }],
    [{ name: 'canonical', label: 'Canonical address', hint: CANONICAL_HINT }],
    [
        { name: 'robots', label: 'Search engines', options: ROBOTS },
        { name: 'schema', label: 'Schema type', options: SCHEMAS },
    ],
]

/** The rows with Override switches: the share image joins them. */
const INHERITED_ROWS = [
    [{ name: 'title', label: 'Meta Title' }],
    [{ name: 'description', label: 'Meta Description', multiline: true }],
    [{ name: 'image', label: 'Share image', image: true }],
    [{ name: 'image_alt', label: 'Share image description' }],
    [{ name: 'canonical', label: 'Canonical address', hint: CANONICAL_HINT }],
    [
        { name: 'robots', label: 'Search engines', options: ROBOTS.slice(1) },
        { name: 'schema', label: 'Schema type', options: SCHEMAS.slice(1) },
    ],
]

/** The share image the record asks for in place of its own: the site's. */
const SITE_IMAGE = '@site'

/** Where a default comes from, in the editor's words, for the sources that don't name a field. */
const SOURCES = {
    content: 'From its content',
    page: "This page's own address",
    section: "The section's type",
    none: 'Nothing yet',
}

/** What the site's own settings give each field. */
const SITE_SOURCES = {
    description: "The site's description",
    image: "The site's share image",
    image_alt: "The site's share image description",
    robots: 'As the site sets it',
}

const OVERRIDDEN = 'Your own. Switch off to go back to the default.'

/** How a default the lists don't offer reads, such as the site's own robots rule. */
const DEFAULT_LABELS = {
    'index, follow': 'Show in search results and follow its links',
}

const LOCK =
    '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-lock" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/></svg>'

const PENCIL =
    '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-pencil" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 20h4l10.5 -10.5a1.5 1.5 0 0 0 -4 -4l-10.5 10.5v4"/><path d="M13.5 6.5l4 4"/></svg>'

function parse(text) {
    try {
        const value = JSON.parse(text)
        return value !== null && typeof value === 'object' && !Array.isArray(value) ? value : null
    } catch {
        return null
    }
}

/** The defaults the host gave the control, or null where it gave none. */
function controlData(element) {
    const data = parse(element.getAttribute('data-control') ?? '')
    const defaults = data?.defaults

    return defaults !== null && typeof defaults === 'object' && !Array.isArray(defaults) ? data : null
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

/** The label the form gives a field, by its column, or the column itself made readable. */
function columnLabel(form, column) {
    const field = [...(form?.querySelectorAll('[name]') ?? [])].find(
        (each) => each.name === column || each.name === `${column}[]`,
    )
    const label = field?.closest('.form__field')?.querySelector('.form__label')?.textContent.trim()

    return label || column.replace(/_/g, ' ')
}

/** Where a field's default comes from, in the editor's words. */
function source(name, entry, form) {
    if (entry.from === 'column') {
        return `From the ${columnLabel(form, entry.column)}`
    }
    if (entry.from === 'site') {
        return SITE_SOURCES[name] ?? "The site's setting"
    }

    return SOURCES[entry.from] ?? SOURCES.none
}

/** A share image's address as the dashboard can show it. */
function imageUrl(value, data) {
    return value === SITE_IMAGE ? (data.siteImage?.url ?? '') : value
}

/**
 * The share image's choices once overridden: the site's, and an image the record holds already. A record that asks
 * for the site's keeps that choice where the site has no share image, so a save never drops what it can't show.
 */
function imageControl(stored, data) {
    const select = document.createElement('select')
    select.className = 'select'
    select.add(new Option("This page's image", ''))
    if (data.siteImage || stored === SITE_IMAGE) {
        select.add(new Option("The site's share image", SITE_IMAGE))
    }
    if (stored !== '' && stored !== SITE_IMAGE) {
        select.add(new Option(`This image: ${stored.split('/').pop()}`, stored))
    }
    select.value = stored

    return select
}

/** The Override switch, in the dashboard's own markup for a setting it inherits. */
function overrideSwitch(field, on) {
    const toggle = document.createElement('label')
    toggle.className = 'inherit__toggle'
    const box = document.createElement('input')
    box.type = 'checkbox'
    box.checked = on
    box.setAttribute('data-seo-override', field.name)
    box.setAttribute('aria-label', `Override ${field.label}`)
    toggle.append(box)
    toggle.insertAdjacentHTML(
        'beforeend',
        `<span class="inherit__icon inherit__icon--inherited">${LOCK}</span>` +
            `<span class="inherit__icon inherit__icon--overridden">${PENCIL}</span>`,
    )

    return [toggle, box]
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

        const data = controlData(element)
        const form = element.closest('form')
        const inputs = new Map()
        const switches = new Map()
        const stored = (name) => (typeof values[name] === 'string' ? values[name] : '')

        const write = () => {
            const written = { ...values }
            for (const [name, input] of inputs) {
                delete written[name]
                const on = switches.get(name)?.checked ?? true
                if (on && input.value.trim() !== '') written[name] = input.value.trim()
            }
            element.value = Object.keys(written).length === 0 ? '' : JSON.stringify(written)
        }

        const inherited = (field, input, id) => {
            const entry = data.defaults[field.name] ?? { value: null, from: 'none', column: null }
            const fallback = entry.value ?? ''
            let kept = stored(field.name)
            const [toggle, box] = overrideSwitch(field, kept !== '')

            const shell = document.createElement('div')
            const area = document.createElement('div')
            area.className = 'inherit__control'
            area.append(input)
            if (field.options && fallback !== '' && !field.options.some(([option]) => option === fallback)) {
                input.add(new Option(DEFAULT_LABELS[fallback] ?? fallback, fallback))
            }

            let preview = null
            if (field.image) {
                preview = document.createElement('img')
                preview.className = 'seo-advanced__preview'
                preview.alt = ''
                preview.addEventListener('error', () => {
                    preview.hidden = true
                })
                area.append(preview)
                box.disabled = !box.checked && input.options.length < 2
            }

            const note = document.createElement('p')
            note.className = 'form__description'
            note.id = `${id}-source`
            input.setAttribute('aria-describedby', note.id)

            const showImage = () => {
                if (preview) {
                    preview.src = box.checked ? imageUrl(input.value, data) : fallback
                    preview.hidden = preview.getAttribute('src') === ''
                }
            }

            const show = () => {
                const on = box.checked
                shell.className = on ? 'inherit inherit--overridden' : 'inherit'
                input.disabled = !on
                if (on) {
                    input.value = kept !== '' ? kept : field.image ? SITE_IMAGE : fallback
                } else {
                    input.value = field.image ? '' : fallback
                }
                note.textContent = on ? OVERRIDDEN : `${source(field.name, entry, form)}, as last saved.`
                showImage()
            }

            box.addEventListener('change', () => {
                if (!box.checked) kept = input.value
                show()
                write()
            })
            const keep = () => {
                kept = input.value
                showImage()
            }
            input.addEventListener('input', keep)
            input.addEventListener('change', keep)

            shell.append(toggle, area)
            switches.set(field.name, box)
            show()

            return [shell, note]
        }

        const column = (field) => {
            const id = `${element.id || element.name}-${field.name}`
            const label = document.createElement('label')
            label.className = 'form__label'
            label.htmlFor = id
            label.textContent = field.label

            const input = field.image ? imageControl(stored(field.name), data) : control(field, stored(field.name))
            input.id = id
            inputs.set(field.name, input)

            const wrapper = document.createElement('div')
            wrapper.className = 'form__field'
            wrapper.append(label, ...(data ? inherited(field, input, id) : [input]))
            if (field.hint) {
                const hint = document.createElement('p')
                hint.className = 'form__description'
                hint.id = `${id}-desc`
                hint.textContent = field.hint
                input.setAttribute(
                    'aria-describedby',
                    [input.getAttribute('aria-describedby'), hint.id].filter(Boolean).join(' '),
                )
                wrapper.append(hint)
            }

            const col = document.createElement('div')
            col.className = 'form__col'
            col.append(wrapper)

            return col
        }
        const rows = (data ? INHERITED_ROWS : ROWS).map((fields) => {
            const row = document.createElement('div')
            row.className = data ? 'form__row seo-advanced' : 'form__row'
            row.append(...fields.map(column))

            return row
        })

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
