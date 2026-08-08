@extends('kopling-moderation::layouts.moderation')

@section('content')
    <div class="flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __("kopling-moderation::moderation.status.$status") }}</h1>

        <div id="moderation-queue-wrapper">
            <div id="moderation-queue" class="flex flex-col gap-4">
                @forelse ($context->getSubjectPaginator() as $flag)
                    @php($target = $targets->get($flag->flaggable_type))
                    <div class="card bg-base-100 border border-base-300">
                        <div class="card-body gap-4">
                            <div class="flex items-center justify-between gap-4 text-sm opacity-70">
                                <span>
                                    {{ __('kopling-moderation::moderation.reported_by', ['name' => $flag->person?->name ?? __('kopling-moderation::moderation.unknown_reporter')]) }}
                                    &middot; {{ $flag->created_at->diffForHumans() }}
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="badge badge-outline">{{ __("kopling-moderation::moderation.reasons.{$flag->reason->value}") }}</span>
                                    @if ($target?->softDeletable && $flag->flaggable?->trashed())
                                        <span class="badge badge-error badge-outline">{{ __('kopling-moderation::moderation.hidden') }}</span>
                                    @endif
                                </span>
                            </div>

                            @if ($flag->note)
                                <p class="italic opacity-80">&ldquo;{{ $flag->note }}&rdquo;</p>
                            @endif

                            <div class="border-t border-base-300 pt-4 @if ($target?->softDeletable && $flag->flaggable?->trashed()) opacity-60 @endif">
                                @if ($target && $flag->flaggable)
                                    @include($target->preview, ['flaggable' => $flag->flaggable, 'flag' => $flag])
                                @else
                                    <p class="opacity-60 italic">{{ __('kopling-moderation::moderation.content_unavailable') }}</p>
                                @endif
                            </div>

                            {{-- Deliberately plain POSTs, no hx-boost -- ModerationController::
                                 hide()/unhide() return an empty 200 for an HX-Request (so the
                                 Card's own hx-post+hx-target="closest .card" trigger can remove
                                 just that card), and hx-boost also sends HX-Request:true with no
                                 explicit target, which would swap the *entire* page body with
                                 that empty response, wiping every other row in the queue too --
                                 not just the one just acted on. --}}
                            <div class="flex items-center justify-end gap-2">
                                @if ($status === 'pending')
                                    <form method="POST" action="{{ route('kopling-moderation::moderation/flag.dismiss', $flag) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm">{{ __('kopling-moderation::moderation.dismiss') }}</button>
                                    </form>

                                    @if ($target?->softDeletable && $flag->flaggable && ! $flag->flaggable->trashed())
                                        <form method="POST" action="{{ route('kopling-core::community/flag.hide', ['type' => $flag->flaggable_type, 'id' => $flag->flaggable_id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-error btn-sm">{{ __('kopling-moderation::moderation.hide') }}</button>
                                        </form>
                                    @endif

                                    {{-- Person is never softDeletable (see ModerationTarget's
                                         own docblock) -- a flagged Person's only real action is
                                         Sanction, not Hide/Delete. --}}
                                    @if ($target && ! $target->softDeletable && $flag->flaggable)
                                        @include('kopling-moderation::queue.sanction-form', ['person' => $flag->flaggable])
                                    @endif
                                @endif

                                @if ($target?->softDeletable && $flag->flaggable?->trashed())
                                    <form method="POST" action="{{ route('kopling-core::community/flag.unhide', ['type' => $flag->flaggable_type, 'id' => $flag->flaggable_id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">{{ __('kopling-moderation::moderation.unhide') }}</button>
                                    </form>
                                @endif

                                @if ($target?->softDeletable && $flag->flaggable)
                                    <form method="POST" action="{{ route('kopling-core::community/flag.destroy', ['type' => $flag->flaggable_type, 'id' => $flag->flaggable_id]) }}"
                                          onsubmit="return confirm('{{ __('kopling-moderation::moderation.confirm_delete_prompt') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-error btn-sm btn-outline">{{ __('kopling-moderation::moderation.delete') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="opacity-60">{{ __('kopling-moderation::moderation.queue_empty') }}</p>
                @endforelse
            </div>
            <x-k::page.pagination :context="$context" target="#moderation-queue-wrapper" />
        </div>
    </div>
@endsection
