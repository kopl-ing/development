@php use Kopling\Core\Ux\Context; @endphp
{{--
    The discussion page for one moment: the moment itself (through core's own card, so it
    keeps its tags/reactions/etc.), then the reply thread and a composer. Sits inside Community's
    own chrome (see k-core/views/community/chrome.blade.php) so it keeps the topbar/sidebar even
    though its route isn't registered under the Community portal's own route group -- Chrome
    resolves the Community portal itself, this page doesn't need to.
--}}
<x-k::community.chrome>
    <div class="flex flex-col gap-4">
        <div>
            <a href="/" class="btn btn-ghost btn-sm">&larr; {{ __('kopling-discussions::messages.back') }}</a>
        </div>

        <x-k::card.card :context="new Context(subject: $moment)" />

        <div class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">
                {{ trans_choice('kopling-discussions::messages.replies', $replies->count(), ['count' => $replies->count()]) }}
            </h2>

            <div id="replies-{{ $moment->id }}" class="flex flex-col gap-3">
                @foreach ($replies as $reply)
                    @include('kopling-discussions::partials.reply', ['reply' => $reply])
                @endforeach
            </div>

            @auth
                <form hx-post="{{ route('kopling-core::community/discussions.reply', $moment->id) }}"
                      hx-target="#replies-{{ $moment->id }}"
                      hx-swap="beforeend"
                      hx-on::after-request="if (event.detail.successful) this.reset()"
                      class="flex flex-col gap-2">
                    <textarea name="body" required rows="3"
                              class="textarea textarea-bordered w-full"
                              placeholder="{{ __('kopling-discussions::messages.composer_placeholder') }}"></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            {{ __('kopling-discussions::messages.composer_submit') }}
                        </button>
                    </div>
                </form>
            @else
                <p class="text-sm opacity-70">{{ __('kopling-discussions::messages.login_to_reply') }}</p>
            @endauth
        </div>
    </div>
</x-k::community.chrome>
