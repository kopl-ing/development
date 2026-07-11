{{--
    Theme picker for the community topbar. Only shows when there's an actual choice to make
    (2+ installed themes). Each option is its own POST form to `theme.set`, so switching needs
    no JavaScript; the response sets the cookie and redirects back, and the whole page renders
    under the newly-active theme. Only daisyUI semantic classes -- it themes itself.
--}}
@if (count($themes) > 1)
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-2" aria-label="{{ __('kopling-core::theme.switch') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125 0-.938.75-1.688 1.688-1.688H16c3.313 0 6-2.688 6-6C22 6.037 17.5 2 12 2z"/>
            </svg>
            <span class="hidden sm:inline">{{ $themes[$active] ?? __('kopling-core::theme.switch') }}</span>
        </div>
        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-30 mt-2 w-48 border border-base-300 p-2 shadow-lg">
            <li class="menu-title text-xs">{{ __('kopling-core::theme.title') }}</li>
            @foreach ($themes as $id => $label)
                <li>
                    <form method="POST" action="{{ route('kopling-core::community/theme.set') }}">
                        @csrf
                        <input type="hidden" name="theme" value="{{ $id }}">
                        <button type="submit" class="flex w-full items-center justify-between {{ $id === $active ? 'active font-semibold' : '' }}">
                            <span>{{ $label }}</span>
                            @if ($id === $active)
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5"/>
                                </svg>
                            @endif
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif
