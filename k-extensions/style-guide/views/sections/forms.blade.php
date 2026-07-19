<section id="forms" class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.forms') }}
    </h2>

    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.input', 'label' => 'Input'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.text-area', 'label' => 'TextArea'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.select', 'label' => 'Select'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.multi-select', 'label' => 'MultiSelect'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.toggle', 'label' => 'Toggle'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.emoji-picker', 'label' => 'EmojiPicker'])
    @include('kopling-style-guide::partials.example', ['path' => 'kopling-style-guide::sections.forms.tag-input', 'label' => 'TagInput'])
</section>
