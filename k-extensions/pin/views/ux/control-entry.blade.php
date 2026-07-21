@if ($pin)
    <div class="flex flex-col gap-1">
        <x-k::modal :label="__('kopling-pin::pin.edit_pin')" class="w-full justify-start">
            <x-slot:trigger>{{ __('kopling-pin::pin.edit_pin') }}</x-slot:trigger>
            <form method="POST" action="{{ route('kopling-core::community/pin.store', $moment) }}"
                  hx-post="{{ route('kopling-core::community/pin.store', $moment) }}"
                  hx-target="#moment-{{ $moment->id }}" hx-swap="outerHTML"
                  hx-on::after-request="if (event.detail.successful) $el.closest('dialog').close()"
                  class="flex flex-col gap-4">
                @csrf
                <x-k::form.select :data="[
                    'name' => 'reason',
                    'label' => __('kopling-pin::pin.reason'),
                    'options' => $reasons,
                    'value' => $pin->reason,
                ]" />
                <x-k::form.input :data="[
                    'name' => 'starts_at',
                    'label' => __('kopling-pin::pin.starts_at'),
                    'type' => 'datetime-local',
                    'value' => optional($pin->starts_at)->format('Y-m-d\TH:i'),
                ]" />
                <x-k::form.input :data="[
                    'name' => 'ends_at',
                    'label' => __('kopling-pin::pin.ends_at'),
                    'type' => 'datetime-local',
                    'value' => optional($pin->ends_at)->format('Y-m-d\TH:i'),
                ]" />
                <x-k::form.multi-select :data="[
                    'name' => 'groups',
                    'label' => __('kopling-pin::pin.groups'),
                    'options' => $groups->pluck('name', 'id'),
                    'value' => $pin->groups->pluck('id'),
                ]" />
                <button type="submit" class="btn btn-primary self-start">{{ __('kopling-pin::pin.save') }}</button>
            </form>
        </x-k::modal>
        <form method="POST" action="{{ route('kopling-core::community/pin.destroy', $moment) }}"
              hx-post="{{ route('kopling-core::community/pin.destroy', $moment) }}"
              hx-target="#moment-{{ $moment->id }}" hx-swap="outerHTML">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm w-full justify-start text-error">{{ __('kopling-pin::pin.unpin') }}</button>
        </form>
    </div>
@else
    <x-k::modal :label="__('kopling-pin::pin.pin')" class="w-full justify-start">
        <x-slot:trigger>{{ __('kopling-pin::pin.pin') }}</x-slot:trigger>
        <form method="POST" action="{{ route('kopling-core::community/pin.store', $moment) }}" class="flex flex-col gap-4">
            @csrf
            <x-k::form.select :data="[
                'name' => 'reason',
                'label' => __('kopling-pin::pin.reason'),
                'options' => $reasons,
            ]" />
            <x-k::form.input :data="[
                'name' => 'starts_at',
                'label' => __('kopling-pin::pin.starts_at'),
                'type' => 'datetime-local',
            ]" />
            <x-k::form.input :data="[
                'name' => 'ends_at',
                'label' => __('kopling-pin::pin.ends_at'),
                'type' => 'datetime-local',
            ]" />
            <x-k::form.multi-select :data="[
                'name' => 'groups',
                'label' => __('kopling-pin::pin.groups'),
                'options' => $groups->pluck('name', 'id'),
            ]" />
            <button type="submit" class="btn btn-primary self-start">{{ __('kopling-pin::pin.save') }}</button>
        </form>
    </x-k::modal>
@endif
