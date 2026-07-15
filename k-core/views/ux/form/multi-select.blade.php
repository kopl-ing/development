<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    <div class="flex flex-col gap-1 max-h-48 overflow-y-auto border border-base-300 rounded-box p-2">
        @forelse ($options as $id => $optionLabel)
            <label class="label cursor-pointer justify-start gap-2">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $id }}" class="checkbox checkbox-sm"
                       @checked(in_array((string) $id, $values, true)) />
                <span>{{ $optionLabel }}</span>
            </label>
        @empty
            <p class="opacity-60 text-sm">{{ __('kopling-core::ux.no_options') }}</p>
        @endforelse
    </div>
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
