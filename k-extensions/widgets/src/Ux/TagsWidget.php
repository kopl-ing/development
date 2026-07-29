<?php

declare(strict_types=1);

namespace Kopling\Widgets\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\Form\IconSearch\IconRenderer;

/**
 * "Heat", not lifetime popularity -- a tag with nothing from the last week doesn't appear at
 * all, and ranking is by how much has happened recently (moments tagged with it, plus replies
 * to any moment carrying it, when discussions is installed), never total post count (a
 * popularity monument that rewards dead tags with big numbers). Faces + a plain recency stamp
 * instead of a count: a timestamp proves life, a number only proves size. No-ops entirely
 * without the tags extension -- `Tag`/`Reply` are referenced by fully-qualified name rather
 * than `use`-imported, same reasoning as everywhere else an optional extension's class is
 * touched from code that must still load without it installed.
 */
class TagsWidget extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-widgets::ux.tags-widget', [
            'tags' => $this->tags(),
        ]);
    }

    protected function tags(): array
    {
        if (! class_exists(\Kopling\Tags\Tag::class)) {
            return [];
        }

        return Cache::remember('kopling-widgets.tags', 60, function () {
            $window = now()->subDays(7);
            $hasReplies = class_exists(\Kopling\Discussions\Reply::class);

            return \Kopling\Tags\Tag::query()
                ->whereHas('moments')
                ->get()
                ->map(fn ($tag) => $this->activityFor($tag, $window, $hasReplies))
                ->filter(fn (array $entry) => $entry['activity']->isNotEmpty())
                ->sortByDesc(fn (array $entry) => $entry['activity']->count())
                ->take(5)
                ->map(fn (array $entry) => $this->present($entry))
                ->values()
                ->all();
        });
    }

    protected function activityFor($tag, Carbon $window, bool $hasReplies): array
    {
        $moments = $tag->moments()
            ->with('person:id,name')
            ->get(['moments.id', 'moments.person_id', 'moments.created_at']);

        // Every (timestamp, person) pair counting as "recent activity" under this tag -- a
        // moment itself posted within the window, or (with discussions installed) a reply
        // within the window to any moment carrying this tag, however old that moment is. A tag
        // stays "hot" as long as people keep replying under it, not just while its moments are
        // freshly posted.
        $activity = collect();

        foreach ($moments as $moment) {
            if ($moment->created_at?->gte($window)) {
                $activity->push(['at' => $moment->created_at, 'person' => $moment->person]);
            }
        }

        if ($hasReplies && $moments->isNotEmpty()) {
            \Kopling\Discussions\Reply::query()
                ->whereIn('moment_id', $moments->pluck('id'))
                ->where('created_at', '>=', $window)
                ->with('person:id,name')
                ->get()
                ->each(fn ($reply) => $activity->push(['at' => $reply->created_at, 'person' => $reply->person]));
        }

        return ['tag' => $tag, 'activity' => $activity->sortByDesc('at')->values()];
    }

    protected function present(array $entry): array
    {
        // Real people who actually posted/replied under this tag in the window -- never
        // filler. Capped at 3 here (not the 5 fetched above): three small avatars plus a "+N"
        // badge reads clearly in a ~256px sidebar; five crammed onto one row didn't leave
        // enough width for anything to actually look like faces rather than noise.
        $contributors = $entry['activity']->pluck('person')->filter()->unique('id')->values();

        return [
            'name' => $entry['tag']->name,
            'slug' => $entry['tag']->slug,
            'color' => $entry['tag']->color,
            'icon' => $entry['tag']->icon ? IconRenderer::svg($entry['tag']->icon, '0.9em') : null,
            'avatars' => $contributors->take(3)
                ->map(fn (Person $person) => ['name' => $person->name, 'color' => Person::colorFor((string) $person->id)])
                ->values()
                ->all(),
            'more_contributors' => max(0, $contributors->count() - 3),
            // A plain ISO8601 string, not a Carbon instance -- the `array` cache driver Pest
            // tests run under never actually serializes anything, so a Carbon object
            // round-trips fine there and the tests stayed green, but the real `file` driver
            // production uses does a genuine PHP serialize()/unserialize(), which doesn't
            // reliably reconstruct Carbon (confirmed live: "incomplete object" on the very next
            // request). A string has no such risk.
            'last_activity' => $entry['activity']->first()['at']->toIso8601String(),
        ];
    }
}
