<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}@if ($required) <span class="text-error" aria-hidden="true">*</span>@endif</legend>
    <select name="{{ $name }}" class="select" @required($required)>
        @foreach ($options as $id => $optionLabel)
            <option value="{{ $id }}" @selected($value === (string) $id)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($description)
        <p class="label whitespace-normal">{{ $description }}</p>
    @endif
</fieldset>
