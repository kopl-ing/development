@if (empty($icons))
    <p class="kop-icon-picker__empty">
        {{ $term === '' ? __('kopling-core::ux.icon_search_prompt') : __('kopling-core::ux.icon_search_no_results') }}
    </p>
@else
    @foreach ($icons as $icon)
        <button type="button" class="kop-icon-picker__option" data-icon-option
                data-icon-id="{{ $icon['id'] }}" title="{{ $icon['label'] }}" aria-label="{{ $icon['label'] }}">
            {!! $icon['icon'] !!}
        </button>
    @endforeach
@endif
