<?php

declare(strict_types=1);

namespace Kopling\Profile\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The signed-in person's own profile link in the account menu. `Item` (the common case for a
 * nav entry) only ever calls `route($route)` with no parameters, which can't express `{person}`
 * -- and `Ux::add()`'s `data` is registered once and cached across every request/actor, so the
 * current actor's id could never live there anyway. Resolving `{person}` from `$context->actor`
 * here, in `render()`, is what makes it per-request instead.
 */
class ProfileLink extends Component
{
    public function __construct(
        public array $data = [],
        public string $surface = 'menu',
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-profile::ux.profile-link', [
            'label' => $this->data['label'],
            'icon' => $this->data['icon'] ?? null,
            'url' => route('kopling-core::community/profile.show', ['person' => $this->context->getActor()]),
            'surface' => $this->surface,
        ]);
    }
}
