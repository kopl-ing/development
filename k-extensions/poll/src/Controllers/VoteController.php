<?php

declare(strict_types=1);

namespace Kopling\Poll\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Kopling\Core\Ux\Context;
use Kopling\Poll\Poll;

class VoteController
{
    use AuthorizesRequests;

    /**
     * Replaces whichever vote(s) this person already cast on this poll, in one transaction --
     * "changing a vote" is a delete-then-recreate, not an update, since single-choice and
     * multiple-choice both reduce to "the current set of options I picked." Same `HX-Request`
     * branch every other action-then-refresh endpoint in this codebase uses -- htmx gets the
     * widget re-rendered in place (targeting `#poll-{id}`), a plain POST gets a redirect back.
     */
    public function store(Request $request, Poll $poll): View|RedirectResponse
    {
        $this->authorize('kopling-poll::vote');

        abort_if($poll->isClosed(), 403);
        abort_unless($poll->isVisibleTo($request->user()), 403);

        $validated = $request->validate([
            'option_ids' => ['required', 'array', $poll->multiple_choice ? 'min:1' : 'size:1'],
            'option_ids.*' => ['uuid', Rule::exists('poll_options', 'id')->where('poll_id', $poll->id)],
        ]);

        $optionIds = $validated['option_ids'];

        if ($poll->multiple_choice && $poll->max_choices !== null) {
            abort_if(count($optionIds) > $poll->max_choices, 422);
        }

        DB::transaction(function () use ($poll, $optionIds, $request) {
            $poll->votes()->where('person_id', $request->user()->id)->delete();

            foreach ($optionIds as $optionId) {
                $poll->votes()->create([
                    'poll_option_id' => $optionId,
                    'person_id' => $request->user()->id,
                ]);
            }
        });

        if (! $request->header('HX-Request')) {
            return back();
        }

        return view('kopling-poll::components.widget', [
            'context' => new Context(subject: $poll->moment),
        ]);
    }
}
