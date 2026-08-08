{{--
    Reachable both after EnforceSanctions kicks a mid-session person out (flashed details, see
    that middleware's own docblock) and after a rejected login attempt lands back here via a
    plain link -- `$details` is empty in the latter case (LoginController shows its own inline
    validation message instead), so this falls back to a generic notice rather than assuming the
    flash data is always present.
--}}
<x-k::portal.layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="card card-border bg-base-100 w-full max-w-sm">
            <div class="card-body gap-4">
                <h1 class="card-title">{{ __('kopling-core::auth.access_blocked_title') }}</h1>

                @if (! empty($details['reason']))
                    <p>{{ __('kopling-core::auth.access_blocked_reason', ['reason' => \Illuminate\Support\Str::headline($details['reason'])]) }}</p>
                @else
                    <p>{{ __('kopling-core::auth.access_blocked') }}</p>
                @endif

                @if (! empty($details['note']))
                    <p class="italic opacity-70">&ldquo;{{ $details['note'] }}&rdquo;</p>
                @endif

                @if (! empty($details['until']))
                    <p class="text-sm opacity-70">
                        {{ __('kopling-core::auth.access_blocked_until', ['until' => $details['until']->format('Y-m-d H:i')]) }}
                    </p>
                @else
                    <p class="text-sm opacity-70">{{ __('kopling-core::auth.access_blocked_permanent') }}</p>
                @endif

                <a href="{{ route('kopling-core::community/community') }}" class="link text-sm">{{ __('kopling-core::auth.back_home') }}</a>
            </div>
        </div>
    </div>
</x-k::portal.layout>
