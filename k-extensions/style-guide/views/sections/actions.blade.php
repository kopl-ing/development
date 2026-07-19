<section id="actions" class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.actions') }}
    </h2>

    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.actions.link', 'label' => 'Link'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.actions.dropdown', 'label' => 'Dropdown'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.actions.modal', 'label' => 'Modal'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.actions.icon', 'label' => 'Icon'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.actions.theme-switcher', 'label' => 'ThemeSwitcher'])
</section>
