{{--
    `<x-k::editor>` is the swappable mount (Ux::replace()-able), not the concrete `NotionEditor`
    implementation directly -- whichever editor is actually registered in
    `kopling-core::editor.body` renders here, which today is always NotionEditor (Core's own
    default).
--}}
<section id="editor" class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.editor') }}
    </h2>

    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.editor.mount', 'label' => 'Editor'])
</section>
