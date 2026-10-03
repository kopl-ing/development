<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\Authentication\AuthSettings;

/**
 * Same shape as laravel/ui's own RedirectsUsers trait, minus the `redirectTo()`-method
 * override hook it also supports -- nothing here needs that yet.
 */
trait RedirectsUsers
{
    protected function redirectTo(): string
    {
        return $this->redirectTo ?? '/';
    }

    /**
     * The configured path when set, otherwise back to the page the person came from.
     */
    protected function redirectAfterAuthentication(Request $request): RedirectResponse
    {
        $path = AuthSettings::redirectPath();
        if ($path === null) {
            return redirect()->intended($this->redirectTo());
        }

        $request->session()->forget('url.intended');

        return redirect($path);
    }
}
