<section id="person" class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.person') }}
    </h2>

    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.person.avatar', 'label' => 'Avatar'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.person.avatar-group', 'label' => 'AvatarGroup'])
</section>
