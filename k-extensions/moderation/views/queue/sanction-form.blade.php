@php
    use Kopling\Core\Moderation\ModerationReason;

    // Computed here, not inline in the :data="[...]" attribute below -- nesting an
    // interpolated double-quoted string inside that already-double-quoted Blade attribute
    // breaks the compiler (it leaked raw PHP text into the rendered page rather than throwing),
    // the same reason ReportControlEntry computes its own $reasons in its PHP class instead.
    $reasonOptions = collect(ModerationReason::cases())
        ->mapWithKeys(fn (ModerationReason $reason) => [$reason->value => __('kopling-moderation::moderation.reasons.'.$reason->value)]);
@endphp
<x-k::modal :label="__('kopling-moderation::moderation.sanction')">
    <x-slot:trigger>{{ __('kopling-moderation::moderation.sanction') }}</x-slot:trigger>
    <form method="POST" action="{{ route('kopling-moderation::moderation/sanction.store', $person) }}" class="flex flex-col gap-4">
        @csrf
        <x-k::form.toggle :data="['name' => 'communication_blocked', 'label' => __('kopling-moderation::moderation.sanction_communication')]" />
        <x-k::form.toggle :data="['name' => 'hide_content', 'label' => __('kopling-moderation::moderation.sanction_visibility')]" />
        <x-k::form.toggle :data="['name' => 'access_blocked', 'label' => __('kopling-moderation::moderation.sanction_access')]" />
        <x-k::form.input :data="[
            'name' => 'access_blocked_until',
            'label' => __('kopling-moderation::moderation.sanction_until'),
            'type' => 'datetime-local',
        ]" />
        <x-k::form.select :data="[
            'name' => 'reason',
            'label' => __('kopling-moderation::moderation.reason'),
            'options' => $reasonOptions,
        ]" />
        <x-k::form.text-area :data="[
            'name' => 'note',
            'label' => __('kopling-moderation::moderation.note'),
        ]" />
        <button type="submit" class="btn btn-error self-start">{{ __('kopling-moderation::moderation.confirm_sanction') }}</button>
    </form>
</x-k::modal>
