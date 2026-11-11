<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}@if ($required) <span class="text-error" aria-hidden="true">*</span>@endif</legend>

    <k-date-range class="flex flex-col gap-2"
                  data-locale="{{ $locale }}"
                  @if ($min) data-min="{{ $min }}" @endif
                  @if ($max) data-max="{{ $max }}" @endif
                  @if ($marked) data-marked="{{ json_encode($marked, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}" @endif>
        <div data-date-range-calendar hidden></div>

        <div class="flex flex-wrap items-center gap-2" data-date-range-result hidden>
            <p class="text-sm" data-date-range-summary data-empty="{{ __('kopling-core::ux.date_range_empty') }}"></p>
            @unless ($required)
                <button type="button" class="btn btn-ghost btn-xs" data-date-range-clear hidden>{{ __('kopling-core::ux.clear') }}</button>
            @endunless
        </div>

        <div class="flex flex-wrap gap-2" data-date-range-fields>
            <label class="flex flex-col gap-1">
                <span class="label">{{ $startLabel }}</span>
                <input type="date" name="{{ $startName }}" value="{{ $start }}" class="input" data-date-range-start @required($required)
                       @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
            </label>
            <label class="flex flex-col gap-1">
                <span class="label">{{ $endLabel }}</span>
                <input type="date" name="{{ $endName }}" value="{{ $end }}" class="input" data-date-range-end @required($required)
                       @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
            </label>
        </div>
    </k-date-range>

    @if ($description)
        <p class="label whitespace-normal">{{ $description }}</p>
    @endif
</fieldset>
