{{-- Auto-registered under the "kopling-example" namespace (vendor + package, hyphenated --
     dropping the vendor would let two different vendors' same-named extensions collide).
     Referenced from anywhere as view('kopling-example::hello'). The badge below is styled
     by this extension's own compiled CSS/JS (resources/css/app.css, resources/js/app.js --
     see CLAUDE.md's "How k-core ships compiled assets"), not core's bundle. --}}
<p>{{ __('kopling-example::messages.hello') }}</p>
<span data-kop-example-badge class="badge rounded-full px-3 py-1">{{ __('kopling-example::messages.hello') }}</span>
