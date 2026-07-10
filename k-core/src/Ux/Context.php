<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\People\Person;
use Kopling\Core\Portal\Portal;

/**
 * The render-time binding a component tree resolves against -- `$subject` is whatever model
 * the tree is actually about (a `Moment`, later something else), `$actor` is whoever's
 * looking at it (`null` for a guest). Passed down unchanged from a tree's root all the way
 * to its leaves, and carried on every `UxEntry` a slot resolves, so a registered component
 * never needs anything threaded through as a loose, positional array -- it reads
 * `$context->subject`/`$context->actor` directly.
 *
 * `$subject` is deliberately `mixed`, not typed to `Moment` -- there's only one bound-model
 * type in the codebase today, so typing this against a shared interface would be inventing
 * structure ahead of a real second need.
 */
class Context
{
    public function __construct(
        public mixed $subject = null,
        public ?Portal $portal = null,
        public ?Request $request = null,
        public ?Person $actor = null,
    ) {
        $this->actor ??= Auth::user();
    }
}
