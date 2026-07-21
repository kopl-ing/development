{{--
    `<x-k::compose.modes>` resolves whichever modes are actually registered app-wide
    (`kopling-core::compose.modes`) -- composer's own `text` plus anything else installed (e.g.
    `poll`'s `vote`), same "shows the real live registration" reasoning the editor section above
    already follows for `<x-k::editor>`.
--}}
<section id="compose" class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.compose') }}
    </h2>

    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.compose.modes', 'label' => 'Compose modes'])
</section>
