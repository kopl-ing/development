<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}@if ($required) <span class="text-error" aria-hidden="true">*</span>@endif</legend>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" class="input w-full" @required($required)
           @isset($min) min="{{ $min }}" @endisset @isset($max) max="{{ $max }}" @endisset @isset($step) step="{{ $step }}" @endisset />
    @if ($description)
        <p class="label whitespace-normal">{{ $description }}</p>
    @endif
</fieldset>
