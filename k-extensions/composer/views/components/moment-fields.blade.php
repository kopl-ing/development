@php
    use Kopling\Composer\Extension;
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Ux\SlotResolver;

    $fieldsEntries = SlotResolver::resolve('kopling-composer::compose.fields', app(Manager::class)->ux());
@endphp
{{--
    The card's own Top/Body/fields/Footer, shared verbatim between a fresh compose box
    (composer.blade.php, `$context`'s subject is `Moment::draft()`) and the edit-in-place form
    (partials/edit.blade.php, `$context`'s subject is the real, persisted Moment) -- every mode
    registered into `Modes::SLOT` (composer's own `text`, poll's `vote`) reads `$context` to tell
    the two apart and prefill itself accordingly, so editing renders identically to composing
    instead of a second, hand-rolled form only the original author's fields happen to cover.
--}}
<x-k::card.top :context="$context" :slot="Extension::TOP_SLOT" :control-slot="Extension::CONTROL_SLOT" />
<x-k::card.body :context="$context" :slot="Extension::BODY_SLOT" />

@if ($fieldsEntries->isNotEmpty())
    <div class="px-4 py-3 sm:px-6">
        <x-k::portal.slot name="kopling-composer::compose.fields" />
    </div>
@endif

<x-k::card.footer :context="$context" :slot="Extension::FOOTER_SLOT" />
