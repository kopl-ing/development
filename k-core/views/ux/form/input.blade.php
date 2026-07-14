<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" class="input w-full" />
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
