@php use Kopling\Core\Ux\Context; @endphp
{{--
    The single source of truth for rendering one Moment as a card -- shared between the
    initial page load (community.blade.php's own @foreach) and LatestMomentsController's
    fragment responses, so a poll-loaded moment renders through the exact same Card/Top/
    Body/Footer extensibility (and any extension's own additions to them) as one rendered at
    page load. Never duplicate this markup elsewhere.
--}}
<x-k::card.card :context="new Context(subject: $moment, portal: $portal)"/>
