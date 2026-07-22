<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Page;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * Page-number controls for whatever `$context`'s subject resolves to via
 * `Context::getSubjectPaginator()` -- a `Builder` subject gets paginated fresh, an
 * already-built `LengthAwarePaginator` subject is used as-is. Takes `Context` rather than a bare
 * paginator so every future signal this might need (the request, the portal, the actor) is
 * already reachable without changing this component's constructor.
 */
class Pagination extends Component
{
    public LengthAwarePaginator $paginator;

    public function __construct(public Context $context)
    {
        $this->paginator = $context->getSubjectPaginator();
    }

    public function render(): View
    {
        return view('kopling-core::page.pagination');
    }
}
