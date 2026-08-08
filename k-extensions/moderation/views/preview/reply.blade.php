@php
    use Kopling\Core\Ux\Context;
    use Kopling\Discussions\Reply;
@endphp
{{-- Same reasoning as preview/moment.blade.php -- the real Reply card, own Top/Body/Footer slots
     (`kopling-discussions::discussions/partials/reply.blade.php` renders the exact same call in
     the actual thread), control menu suppressed for the same trashed-state reason. --}}
<x-k::card.card
    :context="new Context(subject: $flaggable)"
    :top-slot="Reply::TOP_SLOT"
    :badges-slot="Reply::BADGES_SLOT"
    :body-slot="Reply::BODY_SLOT"
    :footer-slot="Reply::FOOTER_SLOT"
    control-slot="kopling-moderation::queue-preview"
    class="bg-base-100 card-dash"
/>
