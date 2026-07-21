<div x-init="defaultMode = '{{ $entries->first()?->id }}'; active = active ?? defaultMode">
    @foreach ($entries as $entry)
        <div x-show="active === '{{ $entry->id }}'" x-cloak
             @input="dirty['{{ $entry->id }}'] = true" @change="dirty['{{ $entry->id }}'] = true">
            <x-dynamic-component :component="$entry->component" :data="$entry->data" />
        </div>
    @endforeach

    @if ($entries->count() > 1)
        <div class="flex items-center gap-1 mt-2 pt-2 overflow-x-auto">
            @foreach ($entries as $entry)
                <div class="indicator">
                    <span class="indicator-item status"
                          :class="dirty['{{ $entry->id }}'] ? 'status-success' : 'status-neutral opacity-0'"></span>
                    <button type="button"
                            class="btn btn-square btn-ghost btn-sm"
                            :class="active === '{{ $entry->id }}' && 'btn-active'"
                            @click="active = '{{ $entry->id }}'"
                            aria-label="{{ $entry->data['label'] }}"
                            title="{{ $entry->data['label'] }}">
                        <x-k::icon :name="$entry->data['icon']" class="size-4" />
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
