@props(['extension'])

{{--
    One installed extension, collapsed by default. Deliberately Alpine (`x-data`/`x-show`), not
    daisyUI's checkbox-based `collapse` component: the enable/disable toggle lives inside the
    same title row that expands/collapses the card, and needs its own `@click.stop` so flipping
    enablement doesn't also flip the collapse -- a checkbox covering the whole title wouldn't
    let a nested toggle opt out of it. Rendered both by settings/index.blade.php (once per
    extension, inside the page-level settings form) and directly by SettingsController::toggle()
    as the htmx swap target -- never wrap this in its own <form>, the settings fields inside it
    are meant to submit as part of the enclosing page-level form.
--}}
<div
    x-data="{ open: false }"
    class="extension-card card card-border {{ $extension['enabled'] ? 'bg-success/10' : 'border-2 border-dashed border-base-300 bg-base-200' }}"
>
    <div class="card-body">
        <div class="flex items-center gap-3 cursor-pointer" @click="open = ! open">
            @if ($extension['iconSm'])
                <img src="{{ $extension['iconSm'] }}" alt="" class="size-6 rounded shrink-0">
            @endif

            <span class="font-semibold flex-1">{{ $extension['name'] }}</span>

            @unless ($extension['cannotBeDisabled'])
                <input type="checkbox"
                       @click.stop
                       hx-post="{{ route('kopling-admin::admin/settings.toggle', $extension['id']) }}"
                       hx-target="closest .extension-card"
                       hx-swap="outerHTML"
                       aria-label="{{ $extension['enabled'] ? __('kopling-admin::messages.disable') : __('kopling-admin::messages.enable') }}"
                       class="toggle toggle-primary"
                       @checked($extension['enabled']) />
            @endunless
        </div>

        {{--
            Description (+ icon) is its own full-width row, not a column squeezed to half the
            card's width alongside the fields -- a field's own `w-full` input previously meant
            "full width of that cramped half-column," not the card, which visibly outgrew the
            room it had the moment a field's label/placeholder ran long.
        --}}
        <div x-show="open" class="mt-4 flex flex-col gap-6">
            <div class="flex flex-col gap-3">
                @if ($extension['iconLg'])
                    <img src="{{ $extension['iconLg'] }}" alt="" class="size-24 rounded-lg">
                @endif
                <p class="opacity-70 text-sm">{{ $extension['description'] }}</p>
            </div>

            @if ($extension['fields']->isNotEmpty())
                <div class="flex flex-col gap-4">
                    @foreach ($extension['fields'] as $entry)
                        <x-dynamic-component
                            :component="$entry['field']->component"
                            :data="array_merge($entry['field']->data, [
                                'name' => $entry['field']->id,
                                'label' => $entry['field']->label,
                                'description' => $entry['field']->description,
                                'value' => $entry['value'],
                            ])"
                        />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
