@php
    use Kopling\Core\Content\Moment;
    use Kopling\Core\People\Person;
    use Kopling\Core\Ux\Context;
    use Illuminate\Support\Str;

    // A fixture Moment/Person -- never persisted, exists only for this preview. `Card`/`Top`/
    // `Body`/`Footer`/`Control` all type their subject as a real `Model` (see Context::$subject),
    // so a plain array/DTO won't satisfy them; an unsaved model instance does, as long as it
    // carries the attributes/relations those components actually read.
    $person = new Person(['name' => 'Jane Doe']);
    $person->id = (string) Str::uuid();

    $moment = new Moment([
        'title' => 'An example moment',
        'body' => 'Plain-text body for the style guide fixture.',
        'body_html' => '<p>This is example moment content, used only to preview the <code>Card</code> composition below -- not a real, persisted Moment.</p>',
    ]);
    $moment->id = (string) Str::uuid();
    $moment->created_at = now()->subHours(3);
    $moment->setRelation('person', $person);
@endphp
{{--
    Renders through the exact same `<x-k::card.card :context="...">` one-liner
    `community/moment.blade.php` uses for a real Moment (see that view's own comment) -- Top/
    Body/Footer/Control resolve whatever's actually registered in their slots, so this shows
    Core's real defaults (Avatar/Author/Timestamp/Control, Content) *and* anything any installed
    extension has added (tags, reactions, ...), not a hand-drawn mock of them. `Card\Author`/
    `Avatar`/`Content`/`Timestamp`/`Row`/`Column` are exercised this way rather than tagged
    directly -- they're `Ux::add()`-only leaves, never invoked as bare `<x-k::card.*>` tags by
    convention (see ComponentCoverageTest's exclusion list for the same reasoning).
--}}
<section id="card" class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.card') }}
    </h2>
    <p class="text-sm text-base-content/70">
        A fixture Moment (never persisted), rendered through the real Card/Top/Body/Footer/Control
        composition -- whatever any installed extension registers into these slots renders here
        too, not just Core's own defaults.
    </p>

    <x-k::card.card :context="new Context(subject: $moment)" />
</section>
