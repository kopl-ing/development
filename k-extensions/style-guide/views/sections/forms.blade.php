<section id="forms" class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.forms') }}
    </h2>

    <div class="grid md:grid-cols-2 gap-6">
        <x-k::form.input :data="[
            'name' => 'example_input',
            'label' => 'Input',
            'description' => 'A single-line setting field.',
            'placeholder' => 'Type here…',
        ]" />

        <x-k::form.text-area :data="[
            'name' => 'example_textarea',
            'label' => 'TextArea',
            'description' => 'A multi-line setting field.',
            'placeholder' => 'Type here…',
        ]" />

        <x-k::form.select :data="[
            'name' => 'example_select',
            'label' => 'Select',
            'description' => 'A single-value setting field.',
            'options' => ['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'],
            'value' => 'system',
        ]" />

        <x-k::form.multi-select :data="[
            'name' => 'example_multi_select',
            'label' => 'MultiSelect',
            'description' => 'A multi-value setting field.',
            'options' => ['admin' => 'Admin', 'moderator' => 'Moderator', 'member' => 'Member'],
            'value' => ['moderator'],
        ]" />

        <x-k::form.toggle :data="[
            'name' => 'example_toggle',
            'label' => 'Toggle',
            'description' => 'A boolean setting field.',
            'value' => true,
        ]" />

        <x-k::form.emoji-picker :data="[
            'name' => 'example_emoji',
            'label' => 'EmojiPicker',
            'description' => 'A single-emoji setting field.',
            'value' => '🎨',
        ]" />

        <div class="md:col-span-2">
            <x-k::form.tag-input :data="[
                'name' => 'example_tag_input',
                'label' => 'TagInput',
                'description' => 'A searchable, server-backed multi-select.',
                'searchUrl' => route('kopling-style-guide::style-guide/tag-input-search'),
                'value' => [['id' => '2', 'label' => 'Engineering']],
            ]" />
        </div>
    </div>
</section>
