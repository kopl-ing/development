@php
    use Kopling\Core\Ux\Form\IconSearch\IconRenderer;
    use Kopling\Tags\Tag;
@endphp
@props(['data' => [], 'context' => null])
{{--
    A reusable tag picker -- a searchable, server-backed multi-select of every installed tag.
    Built on core's `<x-k::form.tag-input>` (Tagify-backed pills, search, selection state) --
    this view only supplies the domain: what a tag is, and where to search for one
    (`routes/web.php`'s own `/_tags/search`, capped at 5, returning `{id, label, color, icon}`
    JSON -- `color`/`icon` are read by `tag-input-tagify.js`'s own custom pill/dropdown
    templates, a generic capability of that component, not something this view has to wire up).

    Registered into `kopling-composer::compose.fields` (see Extension::ux()) so the create-
    moment form gets a tag field without composer ever knowing tags exists. Reusable beyond
    that one registration too -- any future caller (a moment-edit form, say) can render this
    directly with its own `data`.

    `$data` reads `name` (default 'tags' -- also what `Extension::models()`'s `saved()` hook on
    `Moment` reads back, so this string only has to agree with itself), `value` (currently-
    selected ids, falls back to `old($name, [])` for a validation-error redirect-back -- resolved
    to `{id, label}` pairs here since a pill needs its label without a round-trip), `min`/`max`
    (both optional, purely a rendering hint -- server-side enforcement is a separate validation
    rule contributed via `ValidatesModels`, not this component's concern).
--}}
@php
    $name = $data['name'] ?? 'tags';
    $selectedIds = collect($data['value'] ?? old($name, []))->map(fn ($id) => (string) $id)->all();
    $selectedTags = empty($selectedIds) ? collect() : Tag::whereIn('id', $selectedIds)->get();
@endphp
<x-k::form.tag-input :data="[
    'name' => $name,
    'label' => $data['label'] ?? __('kopling-tags::messages.tags'),
    'description' => $data['description'] ?? null,
    'searchUrl' => route('kopling-core::community/tags.search'),
    'value' => $selectedTags->map(fn ($tag) => [
        'id' => $tag->id,
        'label' => $tag->name,
        'color' => $tag->color,
        'icon' => $tag->icon ? IconRenderer::svg($tag->icon, '0.9em', $tag->color) : null,
    ])->values()->all(),
    'min' => $data['min'] ?? null,
    'max' => $data['max'] ?? null,
]" />
