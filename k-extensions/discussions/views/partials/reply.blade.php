@php $name = $reply->person?->name ?? __('kopling-discussions::messages.someone'); @endphp
{{-- One reply. Rendered both in the initial thread and appended by the composer's htmx
     response, so a just-posted reply looks identical to a page-loaded one. --}}
<div class="chat chat-start">
    <div class="chat-header">
        {{ $name }}
        <time class="text-xs opacity-50">{{ $reply->created_at?->diffForHumans() }}</time>
    </div>
    <div class="chat-bubble">{{ $reply->body }}</div>
</div>
