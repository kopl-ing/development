@props(['data' => [], 'context' => null])
{{--
    Discussions' own reply composer -- a plain slot entry now (`kopling-discussions::show.composer`
    in show.blade.php), not markup hardcoded into the page, so a superseding extension (reply-dock)
    can `Ux::remove()` it outright instead of only CSS-hiding a form whose TipTap editor still
    mounts underneath. Guest-only-vs-signed-in-only is a rendering question, not a "does an editor
    exist" one -- the guest fallback stays in show.blade.php's own markup, unconditional on this
    slot, so a superseding extension only ever removes the actual editor-bearing form, never the
    "log in to reply" message a guest is still meant to see. Reads `$context->subject` like every
    other slot leaf.
--}}
@php
    $moment = $context?->getSubject();
@endphp
@auth
    @if ($moment)
        {{-- The editor mounts on a contenteditable region, not a native <textarea>, so
             "reset on post" needs the editor's own imperative clear() alongside
             this.reset() -- form.reset() only touches native form controls. --}}
        <form hx-post="{{ route('kopling-core::community/discussions.reply', $moment->id) }}"
              hx-target="#replies-{{ $moment->id }}"
              hx-swap="beforeend"
              hx-on::after:request="if ((event.detail?.ctx?.response?.status ?? 500) < 400) { this.querySelector('[data-tiptap-editor]')?.kopEditor?.clear(); this.reset() }"
              class="flex flex-col gap-2">
            <x-k::editor name="body" placeholder="{{ __('kopling-discussions::messages.composer_placeholder') }}" />
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    {{ __('kopling-discussions::messages.composer_submit') }}
                </button>
            </div>
        </form>
    @endif
@endauth
