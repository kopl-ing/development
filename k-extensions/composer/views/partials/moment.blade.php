@php use Kopling\Core\Ux\Context; @endphp
{{-- The just-posted moment, rendered through core's card exactly like the feed and poller do
     (community/moment.blade) so extensions' card additions show on it too. --}}
<x-k::card.card :context="new Context(subject: $moment, portal: $portal)"/>
