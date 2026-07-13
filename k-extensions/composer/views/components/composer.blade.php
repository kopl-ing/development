@php $me = auth()->user(); @endphp
{{-- Compose-first: a calm one-line box that grows on focus into title (optional) + body.
     Posting hx-prepends the new moment onto #moments-feed and collapses back. Inline Alpine +
     htmx only — no bundled JS. The [x-cloak] rule now ships as css/app.css, linked onto
     Community pages via Extension::extendsPortals(). --}}
<div x-data="{ open: false }"
     @htmx:after:request="if (($event.detail?.ctx?.response?.status ?? 500) < 400) { open = false; $refs.form.reset() }"
     class="card bg-base-100 hair border border-base-300 rounded-box mb-4 shadow-sm">
    <form x-ref="form"
          hx-post="{{ route('kopling-core::community/compose.store') }}"
          hx-target="#moments-feed"
          hx-swap="afterbegin"
          class="card-body gap-3 p-4">
        @csrf
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 shrink-0 rounded-full bg-primary text-primary-content grid place-items-center text-sm font-bold">
                {{ strtoupper(mb_substr($me?->name ?? '?', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <input type="text" name="title" maxlength="150" x-show="open" x-cloak
                       placeholder="{{ __('kopling-composer::messages.title_placeholder') }}"
                       class="input input-sm w-full font-semibold px-0 mb-1 border-0 focus:outline-none bg-transparent">
                <textarea name="body" required rows="1"
                          @focus="open = true"
                          x-bind:rows="open ? 3 : 1"
                          placeholder="{{ __('kopling-composer::messages.body_placeholder') }}"
                          class="textarea w-full resize-none px-0 border-0 focus:outline-none bg-transparent min-h-0"></textarea>
            </div>
        </div>

        <div x-show="open" x-cloak class="flex items-center justify-end gap-2 pt-1">
            <button type="button" @click="open = false; $refs.form.reset()"
                    class="btn btn-ghost btn-sm">{{ __('kopling-composer::messages.cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm">
                {{ __('kopling-composer::messages.post') }}
            </button>
        </div>
    </form>
</div>
