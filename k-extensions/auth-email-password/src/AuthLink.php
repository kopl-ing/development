<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A single topbar link -- generic on `label`/`route`/`variant` the same way
 * `Kopling\Core\Ux\Portal\Navigation\Item` is generic for side-navigation, so login and
 * register don't need two near-identical component classes.
 */
class AuthLink extends Component
{
    public function __construct(public array $data)
    {
    }

    public function render(): View
    {
        return view('kopling-auth-email-password::auth-link', [
            'label' => $this->data['label'],
            'route' => $this->data['route'],
            'variant' => $this->data['variant'] ?? 'ghost',
        ]);
    }
}
