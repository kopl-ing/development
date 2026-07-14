<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="toggle toggle-primary" @checked($checked) />
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
