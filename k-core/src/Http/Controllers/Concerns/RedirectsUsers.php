<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers\Concerns;

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
}
