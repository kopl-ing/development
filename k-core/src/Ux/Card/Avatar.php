<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;

/**
 * A placeholder-initials avatar -- there's no real image/file upload behind this yet (see
 * Storage's still-unbuilt admin mapping), so this only ever renders initials, never an
 * `<img>`. Swapping in a real image once uploads exist replaces this component's view, not
 * anything that uses it. Always renders whoever `$context`'s own subject is about -- a Moment's
 * `->person` for a card (the usual case: `new Context(subject: $moment)`, shared with this
 * card's other entries), or a `Person` given directly as the subject itself, for a caller with
 * no card/Moment at all (`Community\UserMenu`'s navbar avatar: `new Context(subject:
 * $context->getActor())`). Either way it's the caller's job to bind the right subject; this
 * doesn't fall back to anything on its own.
 */
class Avatar extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $subject = $this->context?->getSubject();
        $person = $subject instanceof Person ? $subject : $subject?->person;

        return view('kopling-core::card.avatar', [
            'initials' => $this->initials($person?->name),
            'name' => $person?->name,
            'color' => $person?->avatarColor(),
        ]);
    }

    protected function initials(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        $words = array_slice(explode(' ', $name), 0, 2);

        return strtoupper(implode('', array_map(fn (string $word) => $word[0], $words)));
    }
}
