@php use Kopling\Core\Ux\Theme\Token; @endphp
{{--
    Every swatch below comes straight from Token::cases() -- the exact, finite set the compiled
    "kopling" daisyUI theme (k-core/src/Ux/css/app.css) exposes as overridable (see Token's own
    docblock). Iterating the enum instead of a hand-copied list means this section can never
    drift from what Theme::css() actually lets an admin re-theme.
--}}
<section id="tokens" class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.tokens') }}
    </h2>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach (Token::cases() as $token)
            <div class="border border-base-300 rounded-box p-3 flex flex-col gap-2">
                @if (str_starts_with($token->value, '--color-'))
                    <div class="h-10 rounded-field border border-base-300"
                         style="background: var({{ $token->value }})"></div>
                @elseif (str_starts_with($token->value, '--font-'))
                    <div class="h-10 flex items-center text-lg" style="font-family: var({{ $token->value }})">
                        Aa Bb Cc
                    </div>
                @else
                    <div class="h-10 flex items-center justify-center">
                        <div class="w-full h-8 bg-base-200 border-2 border-base-content/30"
                             style="border-radius: var({{ $token->value }}, 0); border-width: var(--border, 1px)"></div>
                    </div>
                @endif
                <code class="text-xs text-base-content/70">{{ $token->value }}</code>
            </div>
        @endforeach
    </div>
</section>
