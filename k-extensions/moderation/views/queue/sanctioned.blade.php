@extends('kopling-moderation::layouts.moderation')

@section('content')
    <div class="flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-moderation::moderation.status.sanctioned') }}</h1>

        <div id="moderation-queue-wrapper">
            <div id="moderation-queue" class="flex flex-col gap-4">
                @forelse ($context->getSubjectPaginator() as $person)
                    <div class="card bg-base-100 border border-base-300">
                        <div class="card-body gap-4">
                            <div class="flex items-center gap-3">
                                <x-k::person.avatar :context="new \Kopling\Core\Ux\Context(subject: $person)" size="w-8" />
                                <div>
                                    <p class="font-semibold">{{ $person->name }}</p>
                                    <p class="text-sm opacity-70">{{ $person->email }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if ($person->communication_blocked_at)
                                    <span class="badge badge-warning badge-outline">{{ __('kopling-moderation::moderation.sanction_communication') }}</span>
                                @endif
                                @if ($person->visibility === 'hidden')
                                    <span class="badge badge-warning badge-outline">{{ __('kopling-moderation::moderation.sanction_visibility') }}</span>
                                @endif
                                @if ($person->access_blocked_at)
                                    <span class="badge badge-error badge-outline">
                                        {{ __('kopling-moderation::moderation.sanction_access') }}
                                        @if ($person->access_blocked_until)
                                            &middot; {{ __('kopling-moderation::moderation.sanction_until_short', ['until' => $person->access_blocked_until->format('Y-m-d H:i')]) }}
                                        @endif
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-end">
                                <form method="POST" action="{{ route('kopling-moderation::moderation/sanction.lift', $person) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">{{ __('kopling-moderation::moderation.lift_sanction') }}</button>
                                </form>
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
