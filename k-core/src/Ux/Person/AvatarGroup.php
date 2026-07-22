<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Person;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * daisyUI's `avatar-group` wrapper around a row of `Avatar`s -- the "faces of recent people"
 * pattern used by the discussion teaser and the tags sidebar widget, previously duplicated
 * (identical markup, only size/spacing differed) in each of those views. `$avatars` is a plain
 * iterable of `['name' => string, 'color' => ?string, 'presence' => ?string]` rather than real
 * `Person` models -- callers already have whatever shape their own query produced (a `Person`
 * collection, a plain contributors array) and just map it into this one before passing it in.
 * `$overflow` renders one extra `avatar-placeholder` showing `+N`, daisyUI's own documented
 * avatar-group overflow convention, when there are more contributors than shown here.
 */
class AvatarGroup extends Component
{
    public function __construct(
        public iterable $avatars = [],
        public string $size = 'w-6',
        public ?string $mask = 'mask-squircle',
        public string $spacing = '-space-x-3',
        public int $overflow = 0,
    ) {
    }

    public function render(): View
    {
        return view('kopling-core::person.avatar-group');
    }
}
