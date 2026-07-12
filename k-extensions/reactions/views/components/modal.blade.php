@php use Kopling\Reactions\Reaction; @endphp
{{--
    The reaction picker: one modal for the whole page (registered into the chrome's composer
    slot so it renders once). Event-driven with a LOCAL x-data -- not an Alpine store -- because
    extension js loads after core's Alpine.start(), so an alpine:init-registered store never
    exists. A card's rail "+" does $dispatch('kop-react-open', {url, target}); this listens on
    the window, opens, and submits emoji + optional word via htmx (core adds the CSRF header).
    Styling is css/app.css (core's compiled build doesn't include extension utility classes).
--}}
@auth
    <div x-data="{ open: false, url: null, target: null, emoji: null, word: '' }"
         @kop-react-open.window="open = true; url = $event.detail.url; target = $event.detail.target; emoji = null; word = ''"
         @keydown.escape.window="open = false"
         x-show="open" x-cloak class="kop-rmodal">
        <div class="kop-rmodal__backdrop" @click="open = false"></div>

        <div class="kop-rmodal__panel"
             x-show="open"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="kop-rmodal__head">
                <h2 class="kop-rmodal__title">{{ __('kopling-reactions::messages.add_reaction') }}</h2>
                <button type="button" @click="open = false" class="btn btn-ghost btn-sm btn-square"
                        aria-label="{{ __('kopling-reactions::messages.close') }}">✕</button>
            </div>

            {{-- emoji picker --}}
            <div class="kop-rmodal__grid">
                @foreach (Reaction::PALETTE as $emoji)
                    <button type="button" class="kop-rmodal__emoji"
                            @click="emoji = @js($emoji)"
                            :class="emoji === @js($emoji) && 'is-picked'">{{ $emoji }}</button>
                @endforeach
            </div>

            {{-- optional short word --}}
            <div>
                <div class="kop-rmodal__wordhead">
                    <span>{{ __('kopling-reactions::messages.add_word') }} · {{ __('kopling-reactions::messages.optional') }}</span>
                    <span x-text="word.length + '/{{ Reaction::WORD_MAX }}'"></span>
                </div>
                <input type="text" maxlength="{{ Reaction::WORD_MAX }}" x-model="word"
                       placeholder="{{ __('kopling-reactions::messages.say_it') }}"
                       @keydown.enter.prevent="if (emoji) { window.htmx.ajax('POST', url, { target, swap: 'outerHTML', values: { emoji, word } }); open = false }"
                       class="input input-bordered input-sm w-full">
                <div class="kop-rmodal__quips">
                    @foreach ((array) __('kopling-reactions::messages.quips') as $quip)
                        <button type="button" class="badge badge-ghost" @click="word = @js($quip)">{{ $quip }}</button>
                    @endforeach
                </div>
            </div>

            {{-- live preview + submit --}}
            <div class="kop-rmodal__foot">
                <span class="kop-rmodal__preview" x-show="emoji">
                    <span x-text="emoji"></span>
                    <span x-show="word.trim()" x-text="'“' + word.trim() + '”'"></span>
                </span>
                <button type="button" :disabled="!emoji"
                        @click="window.htmx.ajax('POST', url, { target, swap: 'outerHTML', values: { emoji, word } }); open = false"
                        class="btn btn-primary btn-sm" style="margin-inline-start:auto">{{ __('kopling-reactions::messages.word_submit') }}</button>
            </div>
        </div>
    </div>
@endauth
