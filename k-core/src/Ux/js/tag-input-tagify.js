// The real Tagify payload -- dynamically import()ed by tag-input.js, never loaded on a page
// with no tag-input mount point. `@yaireo/tagify` (MIT, github.com/yairEO/tagify) owns pill/
// keyboard-nav/ARIA UI entirely; this only wires it to the caller's search endpoint and keeps a
// set of real `name="{name}[]"` hidden inputs in sync with the current selection -- see
// tag-input.blade.php's own docblock for why that sync exists instead of trusting Tagify's own
// serialization.
//
// Remote-search wiring follows Tagify's own documented async-whitelist pattern exactly
// (github.com/yairEO/tagify#readme, "Async whitelist"): listen to the `input` event, null out
// `tagify.whitelist` while a request is in flight, `tagify.loading(true)`, replace `.whitelist`
// with the response, then `tagify.loading(false).dropdown.show(query)`. An in-flight request is
// aborted if a new one starts before it resolves.
import Tagify from '@yaireo/tagify';

function debounce(fn, delay) {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

// `color`/`icon` ride through unchanged when a caller's search/initial-value response includes
// them (tags' own `/_tags/search` and `views/components/select.blade.php` do; nothing else does
// yet) -- `undefined` otherwise, which the custom templates below already treat as "render
// nothing extra". This keeps TagInput itself domain-agnostic: it renders whatever optional
// fields a caller's data happens to carry, without knowing or caring what a "tag" is.
function toWhitelistItem(item) {
    return { value: item.label, id: item.id, color: item.color, icon: item.icon };
}

// Same structural shape as Tagify's own default `tag`/`dropdownItem` templates (github.com/
// yairEO/tagify, src/parts/templates.js) -- only insertion is one optional swatch/icon pair
// right before the label -- so Tagify's own remove/edit/ARIA/dedupe behaviour (all of which
// read the rest of this markup) keeps working unchanged.
const templates = {
    tag(tagData) {
        const _s = this.settings;

        return `<tag title="${tagData.title || tagData.value}"
            contenteditable='false'
            tabIndex="${_s.a11y.focusableTags ? 0 : -1}"
            class="${_s.classNames.tag} ${tagData.class || ''}"
            ${this.getAttributes(tagData)}>
            <x title='' tabIndex="${_s.a11y.focusableTags ? 0 : -1}" class="${_s.classNames.tagX}" role='button' aria-label='remove tag'></x>
            <div>
                ${tagData.color ? `<span class="kop-tag-swatch" style="background-color:${tagData.color}"></span>` : ''}
                ${tagData.icon || ''}
                <span ${_s.mode === 'select' && _s.userInput ? "contenteditable='true'" : ''} autocapitalize="false" autocorrect="off" spellcheck='false' class="${_s.classNames.tagText}">${tagData[_s.tagTextProp] || tagData.value}</span>
            </div>
        </tag>`;
    },
    dropdownItem(item) {
        const classNames = this.settings.classNames;

        return `<div ${this.getAttributes(item)}
            class="${classNames.dropdownItem} ${this.isTagDuplicate(item.value) ? classNames.dropdownItemSelected : ''} ${item.class || ''}"
            tabindex="0"
            role="option">
            ${item.color ? `<span class="kop-tag-swatch" style="background-color:${item.color}"></span>` : ''}
            ${item.icon || ''}
            ${item.mappedValue || item.value}
        </div>`;
    },
};

/**
 * Mounts one tag input on `node` (a `[data-tag-input]` element) and returns its imperative API.
 */
export function mount(node) {
    const searchUrl = node.dataset.searchUrl;
    const name = node.dataset.name;
    const max = node.dataset.max ? Number(node.dataset.max) : undefined;
    const initial = JSON.parse(node.dataset.initialValue || '[]');
    const field = node.querySelector('[data-tag-input-field]');

    // Tagify writes its own JSON-serialized value back into this same element -- naming it
    // clearly as a throwaway makes that visible in devtools, and keeps it unambiguous against
    // the real `name="{name}[]"` inputs `syncHidden()` maintains separately below.
    field.name = `_${name}_tagify`;

    const tagify = new Tagify(field, {
        enforceWhitelist: true,
        // Seeded with the already-selected items too, not just as `value` -- enforceWhitelist
        // would otherwise reject them at construction time, since the real whitelist only ever
        // arrives later, from a search response (see "Persisted data" in Tagify's own docs).
        whitelist: initial.map(toWhitelistItem),
        value: initial.map(toWhitelistItem),
        maxTags: max,
        templates,
        dropdown: {
            enabled: 0, // show suggestions immediately on focus, not after N characters typed
            maxItems: 5,
            closeOnSelect: false, // stay open after picking one, for picking several in a row
            highlightFirst: true,
        },
    });

    function syncHidden() {
        // Re-queried on every call rather than trusting the `hiddenContainer` reference cached
        // at mount time -- defensive against Tagify's own DOM manipulation of everything else
        // inside `node` on init/destroy.
        const container = node.querySelector('[data-tag-input-hidden]');

        if (!container) {
            return;
        }

        container.innerHTML = '';

        tagify.value.forEach((tag) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `${name}[]`;
            input.value = tag.id;
            container.appendChild(input);
        });
    }

    let controller;

    function search(query) {
        tagify.whitelist = null;
        controller?.abort();
        controller = new AbortController();
        tagify.loading(true);

        fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, { signal: controller.signal })
            .then((response) => response.json())
            .then((results) => {
                tagify.whitelist = results.map(toWhitelistItem);
                tagify.loading(false).dropdown.show(query);
            })
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    tagify.loading(false);
                }
            });
    }

    const debouncedSearch = debounce((event) => search(event.detail.value), 300);

    tagify.on('input', debouncedSearch);
    tagify.on('focus', () => search(''));
    tagify.on('add', syncHidden);
    tagify.on('remove', syncHidden);

    syncHidden();

    return {
        destroy: () => tagify.destroy(),
    };
}
