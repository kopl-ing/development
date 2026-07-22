<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Person;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;

/**
 * A placeholder-initials avatar -- there's no real image/file upload behind this yet (see
 * Storage's still-unbuilt admin mapping), so this only ever renders initials, never an
 * `<img>`. Wraps daisyUI's own `avatar`/`avatar-placeholder` classes: `$mask` picks a daisyUI
 * mask utility (`mask-squircle` by default, `null` falls back to a plain circle), `$size` is a
 * raw `w-*`/`h-*` class string, `$presence` toggles `avatar-online`/`avatar-offline`.
 *
 * Resolves whoever `$context`'s own subject is about -- a Moment's `->person` for a card (the
 * usual case: `new Context(subject: $moment)`), or a `Person` given directly as the subject
 * itself, for a caller with no card/Moment at all (`Community\UserMenu`'s navbar avatar). `$name`/
 * `$color` are there for callers with no real `Person`/`Context` to hand at all -- an
 * `AvatarGroup` iterating plain contributor arrays (`['name' => ..., 'color' => ...]`), or a
 * caller that wants a color other than `Person::avatarColor()` (the reply composer's own
 * "you" gradient) -- and win over whatever `$context` would have resolved.
 */
class Avatar extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
        public ?string $name = null,
        public ?string $color = null,
        public ?string $mask = 'mask-squircle',
        public string $size = 'w-8 sm:w-10',
        public ?string $presence = null,
    ) {
    }

    public function render(): View
    {
        $subject = $this->context?->getSubject();
        $person = $subject instanceof Person ? $subject : $subject?->person;

        $name = $this->name ?? $person?->name;

        return view('kopling-core::person.avatar', [
            'initials' => $this->initials($name),
            'name' => $name,
            'color' => $this->color ?? $person?->avatarColor(),
            'mask' => $this->mask,
            'size' => $this->size,
            'presence' => $this->presence,
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
