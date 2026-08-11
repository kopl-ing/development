@php
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Ux\Compose\Modes;
    use Kopling\Core\Ux\Context;
    use Kopling\Core\Ux\SlotResolver;

    $context = new Context(subject: $moment);
    // Seeded here rather than left to `ux/compose/modes.blade.php`'s own `x-init` (a nested
    // child element) to fill in -- see composer.blade.php's own identical computation for why:
    // this element's own `x-data` literal is what Alpine reliably evaluates the instant it
    // binds this freshly htmx-swapped-in card, with no dependency on a separate child element's
    // `x-init` also having run yet.
    $defaultMode = SlotResolver::resolve(Modes::SLOT, app(Manager::class)->ux())->first()?->id;
@endphp
{{--
    The edit form, swapped in over the moment's own `.card` (see
    ux/edit-control-entry.blade.php's own `hx-target="closest .card"`) -- the exact same
    Top/Body/fields/Footer `composer.blade.php` renders for a fresh compose box, just bound to
    the real, persisted Moment instead of `Moment::draft()`, so editing covers whatever any
    extension's own compose mode contributes (poll included) rather than only composer's own
    title/body fields. `open: true` skips the focus-to-expand choreography a brand new compose
    box uses -- there's nothing to reveal, this is already what's being edited.
--}}
<div id="moment-{{ $moment->id }}"
     x-data="{
        open: true,
        active: '{{ $defaultMode }}',
        defaultMode: '{{ $defaultMode }}',
        dirty: {},
     }"
     class="card bg-base-100 hair border border-base-300 rounded-box mb-4 shadow-sm">
    <form hx-post="{{ route('kopling-core::community/compose.update', $moment) }}"
          hx-target="closest .card" hx-swap="outerHTML"
          class="divide-y divide-base-content/10">
        @csrf
        <input type="hidden" name="compose_mode" :value="active">

        @include('kopling-composer::components.moment-fields', ['context' => $context])
    </form>
</div>
