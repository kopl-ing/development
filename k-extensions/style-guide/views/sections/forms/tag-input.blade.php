{{-- searchUrl points at your own GET endpoint returning [{"id": "...", "label": "..."}, ...]. --}}
<x-k::form.tag-input :data="[
    'name' => 'example_tag_input',
    'label' => 'TagInput',
    'description' => 'A searchable, server-backed multi-select.',
    'searchUrl' => route('kopling-style-guide::style-guide/tag-input-search'),
    'value' => [['id' => '2', 'label' => 'Engineering']],
]" />
