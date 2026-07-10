{{--
    A distinct slot map from the Admin portal on purpose -- this is the point of the whole
    exercise: each Portal's layout defines its own named regions, not one shared shape every
    Portal is forced into. sidebar/rail/composer are still real, resolvable, empty slots (see
    CLAUDE.md/decisions.md) -- nothing registers into them yet. The card feed queries real
    Moment rows -- see Kopling\Core\Content\Moment and Kopling\Core\Ux\Card\Card's own
    extensibility (Top/Body/Footer resolve core::card.header/.body/.footer, each with its own
    Context binding). Only daisyUI semantic classes (bg-base-*, text-base-content, border-
    base-*) are used here, never a raw color -- keeps this open for the runtime theme-token
    system (see Kopling\Core\Ux\Theme).
--}}
@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<\Kopling\Core\Content\Moment> $moments */
$moments = $context->subject;
/** @var \Kopling\Core\Portal\Portal $portal */
$portal = $context->portal;
@endphp
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
            <div class="flex-1">
                <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
            </div>
            <div class="flex-none gap-2 px-4">
                <x-k::portal.slot name="core::community.topbar" />
            </div>
        </header>

        <div class="flex flex-1">
            <aside class="w-64 bg-base-100 border-r border-base-300 shrink-0">
                <ul class="menu p-4">
                    <x-k::portal.slot name="core::community.sidebar" />
                </ul>
            </aside>

            <main class="flex-1 p-6">
                <div class="max-w-2xl mx-auto">
                    <div role="tablist" class="tabs tabs-border mb-4">
                        <button role="tab" class="tab tab-active">Latest</button>
                        <button role="tab" class="tab">Top</button>
                        <button role="tab" class="tab">New</button>
                    </div>

                    <x-k::portal.slot name="core::community.content-top" />

                    <div class="flex flex-col gap-4">
                        @foreach ($moments as $moment)
                            <x-k::card.card :context="new \Kopling\Core\Ux\Context(subject: $moment, portal: $portal)" />
                        @endforeach
                    </div>
                </div>
            </main>

            <aside class="w-72 border-l border-base-300 p-4 hidden xl:block">
                <x-k::portal.slot name="core::community.rail" />
            </aside>
        </div>

        <footer class="border-t border-base-300 p-4">
            <x-k::portal.slot name="core::community.composer" />
        </footer>
    </div>
</x-k::portal.layout>
