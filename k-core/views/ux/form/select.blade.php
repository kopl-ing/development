<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    <select name="{{ $name }}" class="select">
        @foreach ($options as $id => $optionLabel)
            <option value="{{ $id }}" @selected($value === (string) $id)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
