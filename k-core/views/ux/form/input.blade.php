<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}@if ($required) <span class="text-error" aria-hidden="true">*</span>@endif</legend>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" class="input w-full" @required($required) />
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
