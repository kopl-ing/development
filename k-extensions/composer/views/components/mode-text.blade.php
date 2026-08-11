@props(['data' => [], 'context' => null])
@php
    $moment = $context?->getSubject();
@endphp

<div x-ref="editor">
    <x-k::editor name="body" :value="$moment?->body" placeholder="{{ __('kopling-composer::messages.body_placeholder') }}" />
</div>
