@php use Kopling\Core\Ux\Context; @endphp
<x-k::community.chrome>
    <div id="profile">
        <div id="hero" class="flex gap-4 sm:gap-8">
            <x-k::person.avatar size="w-40" :context="new Context(subject: $person)" />

            <div class="flex flex-col gap-2 sm:gap-4">
                <h1 class="font-extrabold font-sans text-4xl"><x-k::card.author :context="new Context(subject: $person)" /></h1>
            </div>
        </div>

        <div role="tablist" class="tabs tabs-lift my-6 px-4">
            <label class="tab bg-base-200 hover:bg-base-300">
                <input type="radio" name="posts" checked="checked" />
                @choice('kopling-core::community.moments', $moments->total())
            </label>
            <div class="tab-content bg-base-200 border-base-300 p-6">
                <div id="moments-feed" class="flex flex-col gap-4 sm:gap-8">
                    @foreach ($moments as $moment)
                        @include('kopling-core::community.moment', ['moment' => $moment])
                    @endforeach

                    <x-k::page.pagination :context="$context" target="#moments-feed" />
                </div>
            </div>
        </div>
    </div>

</x-k::community.chrome>