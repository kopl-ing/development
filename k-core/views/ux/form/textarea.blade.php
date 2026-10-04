<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    <textarea name="{{ $name }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}" class="textarea w-full">{{ $value }}</textarea>
    @if ($description)
        <p class="label whitespace-normal">{{ $description }}</p>
    @endif
</fieldset>
