<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}@if ($required) <span class="text-error" aria-hidden="true">*</span>@endif</legend>

    <k-calendar class="flex flex-col gap-2"
                data-mode="{{ $mode }}"
                data-locale="{{ $locale }}"
                @if ($min) data-min="{{ $min }}" @endif
                @if ($max) data-max="{{ $max }}" @endif
                @if ($required) data-required @endif
                @if ($marked) data-marked="{{ json_encode($marked, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}" @endif>
        <div data-calendar-host hidden></div>

        @if ($mode === 'range')
            <div class="flex flex-wrap items-center gap-2" data-calendar-result hidden>
                <p class="text-sm" data-calendar-summary data-empty="{{ __('kopling-core::ux.calendar_range_empty') }}"></p>
                @unless ($required)
                    <button type="button" class="btn btn-ghost btn-xs" data-calendar-clear hidden>{{ __('kopling-core::ux.clear') }}</button>
                @endunless
            </div>

            <div class="flex flex-wrap gap-2" data-calendar-fields>
                <label class="flex flex-col gap-1">
                    <span class="label">{{ $startLabel }}</span>
                    <input type="date" name="{{ $startName }}" value="{{ $start }}" class="input" data-calendar-start @required($required)
                           @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
                </label>
                <label class="flex flex-col gap-1">
                    <span class="label">{{ $endLabel }}</span>
                    <input type="date" name="{{ $endName }}" value="{{ $end }}" class="input" data-calendar-end @required($required)
                           @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
                </label>
            </div>
        @else
            <p class="text-sm" data-calendar-summary data-empty="{{ __('kopling-core::ux.calendar_dates_empty') }}" hidden></p>

            <div class="flex flex-col gap-2" data-calendar-rows
                 data-name="{{ $name }}" @if ($time) data-time @endif
                 data-date-label="{{ $dateLabel }}" data-time-label="{{ $timeLabel }}">
                @foreach ($rows as $i => $row)
                    <div class="flex flex-wrap items-center gap-2" data-calendar-row>
                        <input type="date" name="{{ $time ? "{$name}[{$i}][date]" : "{$name}[]" }}" value="{{ $row['date'] }}" class="input" aria-label="{{ $dateLabel }}" data-calendar-date
                               @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
                        @if ($time)
                            <input type="time" name="{{ $name }}[{{ $i }}][time]" value="{{ $row['time'] }}" class="input w-32" aria-label="{{ $timeLabel }}" data-calendar-time>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </k-calendar>

    @if ($description)
        <p class="label whitespace-normal">{{ $description }}</p>
    @endif
</fieldset>
