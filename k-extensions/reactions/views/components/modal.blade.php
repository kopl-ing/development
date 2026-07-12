@php use Kopling\Reactions\Reaction; @endphp
{{--
    The reaction picker: one modal for the whole page (registered into the chrome's composer
    slot so it renders once), driven by the `reactions` Alpine store (js/app.js). Any card's
    rail "+" calls $store.reactions.show(url, target) to open it against that moment; submit
    posts emoji + optional word via htmx. Authed only -- guests never react.
--}}
@auth
    <div x-data x-show="$store.reactions.open" x-cloak
         class="fixed inset-0 z-[60] grid place-items-center p-4"
         @keydown.escape.window="$store.reactions.close()">
        <div class="absolute inset-0 bg-neutral/40" @click="$store.reactions.close()"></div>

        <div class="relative w-full max-w-sm card bg-base-100 border border-base-300 rounded-box shadow-xl p-4 flex flex-col gap-3.5"
             x-show="$store.reactions.open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-lg leading-tight">{{ __('kopling-reactions::messages.add_reaction') }}</h2>
                <button type="button" @click="$store.reactions.close()"
                        class="btn btn-ghost btn-sm btn-square" aria-label="{{ __('kopling-reactions::messages.close') }}">✕</button>
            </div>

            {{-- emoji picker --}}
            <div class="grid grid-cols-6 gap-1.5">
                @foreach (Reaction::PALETTE as $emoji)
                    <button type="button" @click="$store.reactions.emoji = @js($emoji)"
                            class="aspect-square rounded-box text-2xl grid place-items-center hover:bg-base-200 transition-colors"
                            :class="$store.reactions.emoji === @js($emoji) ? 'bg-base-300 ring-2 ring-primary' : ''">{{ $emoji }}</button>
                @endforeach
            </div>

            {{-- optional short word --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold uppercase tracking-wide opacity-60">
                        {{ __('kopling-reactions::messages.add_word') }}
                        <span class="opacity-60">· {{ __('kopling-reactions::messages.optional') }}</span>
                    </span>
                    <span class="text-xs font-mono opacity-50" x-text="$store.reactions.word.length + '/{{ Reaction::WORD_MAX }}'"></span>
                </div>
                <input type="text" maxlength="{{ Reaction::WORD_MAX }}" x-model="$store.reactions.word"
                       placeholder="{{ __('kopling-reactions::messages.say_it') }}"
                       @keydown.enter.prevent="$store.reactions.submit()"
                       class="input input-bordered input-sm w-full">
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach ((array) __('kopling-reactions::messages.quips') as $quip)
                        <button type="button" @click="$store.reactions.word = @js($quip)"
                                class="badge badge-ghost cursor-pointer hover:badge-neutral">{{ $quip }}</button>
                    @endforeach
                </div>
            </div>

            {{-- live preview + submit --}}
            <div class="flex items-center gap-2 pt-2 border-t border-base-200">
                <span class="text-sm opacity-70" x-show="$store.reactions.emoji">
                    <span x-text="$store.reactions.emoji"></span>
                    <span class="italic" x-show="$store.reactions.word.trim()" x-text="'“' + $store.reactions.word.trim() + '”'"></span>
                </span>
                <button type="button" @click="$store.reactions.submit()" :disabled="!$store.reactions.emoji"
                        class="btn btn-primary btn-sm ms-auto">{{ __('kopling-reactions::messages.word_submit') }}</button>
            </div>
        </div>
    </div>
@endauth
