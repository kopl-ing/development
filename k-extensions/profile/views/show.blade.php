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
            <x-kopling-profile::ux.tabs :context="new Context(subject: $person)" />
        </div>
    </div>

</x-k::community.chrome>