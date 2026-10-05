<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}@if ($required) <span class="text-error" aria-hidden="true">*</span>@endif</legend>
    <div class="flex items-center gap-2">
        <select name="{{ $name }}" class="select" @required($required)>
            @foreach ($options as $id => $optionLabel)
                <option value="{{ $id }}" @selected($value === (string) $id)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        {{ $slot ?? '' }}
    </div>
    @if ($description)
        <p class="label whitespace-normal">{{ $description }}</p>
    @endif
</fieldset>
