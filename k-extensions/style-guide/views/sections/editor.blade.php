{{--
    `<x-k::editor>` is the swappable mount (Ux::replace()-able), not the concrete `NotionEditor`
    implementation directly -- same "invoke the slot-resolving wrapper, not the registered leaf"
    rule as Card's Top/Body/Footer/Control below. Whichever editor is actually registered in
    `kopling-core::editor.body` renders here, which today is always NotionEditor (Core's own
    default), but wouldn't need this section to change if that ever stopped being true.
--}}
<section id="editor" class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.editor') }}
    </h2>

    <x-k::editor name="example_body" placeholder="Write something…" />
</section>
