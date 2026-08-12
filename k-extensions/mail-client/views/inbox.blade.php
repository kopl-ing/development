@extends('kopling-mail-client::layouts.mail')

@section('content')
    {{--
        daisyUI drawer: drawer-content (reading pane, wide/primary) + drawer-side (message list,
        narrow) -- `lg:drawer-open` keeps both permanently visible side by side at that
        breakpoint. Below it, the checkbox starts checked when nothing's selected yet (list shown
        as the default overlay), then gets closed once a message is picked, revealing the reading
        pane underneath.
    --}}
    <div class="drawer lg:drawer-open h-[calc(100vh-8rem)]">
        <input id="mail-drawer" type="checkbox" class="drawer-toggle" {{ $selected ? '' : 'checked' }} />

        {{--
            `htmx:after:settle` fires on the swapped-in element itself, not on whatever triggered
            the request (CLAUDE.md gotcha) -- this div is the actual hx-target for every message
            row's hx-get (messages/list.blade.php) and survives across swaps unchanged (only its
            innerHTML is replaced), so it's the right, stable place for this listener rather than
            re-attaching it per row. Inert on lg+, where lg:drawer-open keeps the list statically
            visible regardless of the checkbox.
        --}}
        <div class="drawer-content h-full overflow-y-auto" id="mail-reading-pane"
             hx-on:htmx:after:settle="document.getElementById('mail-drawer').checked = false">
            @if ($selected)
                @include('kopling-mail-client::messages.show', ['message' => $selected])
            @else
                @include('kopling-mail-client::messages.empty')
            @endif
        </div>

        <div class="drawer-side lg:h-full">
            <label for="mail-drawer" aria-label="{{ __('kopling-mail-client::messages.close_message_list') }}" class="drawer-overlay lg:hidden"></label>
            <div class="bg-base-100 w-full sm:w-96 h-full border-r border-base-300 overflow-y-auto">
                @include('kopling-mail-client::messages.list')
            </div>
        </div>
    </div>
@endsection
