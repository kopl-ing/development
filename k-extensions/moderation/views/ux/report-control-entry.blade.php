@if ($flaggable && ! $isOwnContent)
    <x-k::modal :label="__('kopling-moderation::moderation.report')">
        <x-slot:trigger>{{ __('kopling-moderation::moderation.report') }}</x-slot:trigger>
        <form method="POST" action="{{ route('kopling-core::community/flag.store', ['type' => $type, 'id' => $flaggable->id]) }}" class="flex flex-col gap-4">
            @csrf
            <x-k::form.select :data="[
                'name' => 'reason',
                'label' => __('kopling-moderation::moderation.reason'),
                'options' => $reasons,
            ]" />
            <x-k::form.text-area :data="[
                'name' => 'note',
                'label' => __('kopling-moderation::moderation.note'),
            ]" />
            <button type="submit" class="btn btn-primary self-start">{{ __('kopling-moderation::moderation.submit_report') }}</button>
        </form>
    </x-k::modal>
@endif
