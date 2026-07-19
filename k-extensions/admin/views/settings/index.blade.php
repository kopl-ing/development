@extends('kopling-admin::layouts.admin')

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-6">{{ __('kopling-admin::messages.settings') }}</h1>

        @if ($extensions->isEmpty())
            <p class="opacity-60">{{ __('kopling-admin::messages.no_extensions') }}</p>
        @else
            {{--
                `dirty` starts false and flips true on the form's own first `input`/`change` --
                Alpine's event listeners bubble up from every descendant field (text/textarea via
                `input`, checkbox/toggle/select via `change`), so this is one flag for the whole
                form rather than each field tracking its own original value. Not a true dirty-diff
                (toggling a value back to its original doesn't re-disable Save), but matches the
                ask without needing every `Ux\Form\*` component to know its own initial value.
            --}}
            <form method="POST" action="{{ route('kopling-admin::admin/settings.store') }}"
                  class="flex flex-col gap-4"
                  x-data="{ dirty: false }"
                  @input="dirty = true"
                  @change="dirty = true">
                @csrf
                @foreach ($extensions as $extension)
                    <x-kopling-admin::settings.partials.card :extension="$extension" />
                @endforeach

                {{--
                    `sticky bottom-0`, not `fixed` -- stays pinned to the viewport bottom only
                    while scrolled within the form's own height, then scrolls away naturally past
                    the last card, rather than permanently floating over unrelated page chrome.
                    The wrapping `<div class="... pb-20">` above keeps this from ever overlapping
                    the last card's own content once scrolled all the way down.
                --}}
                <div class="sticky bottom-0 -mx-6 px-4 py-3 -mb-6 bg-base-100 border-t border-base-300">
                    <button type="submit" class="btn btn-primary" :disabled="! dirty">{{ __('kopling-admin::messages.save') }}</button>
                </div>
            </form>
        @endif
    </div>
@endsection
