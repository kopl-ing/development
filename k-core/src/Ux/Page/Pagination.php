<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Page;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * Page-number controls for whatever `$context`'s subject resolves to via
 * `Context::getSubjectPaginator()`. Takes `Context` rather than a bare paginator so every future
 * signal this might need (the request, the portal, the actor) is already reachable without
 * changing this component's constructor.
 *
 * `$target` is an optional CSS selector (an id, almost always) naming a wrapper the caller
 * already renders around both the paginated items and this component itself -- when given, page
 * links become an `hx-boost`ed refresh of just that wrapper instead of a full page navigation.
 * Deliberately scoped to this component's own `<nav>`, not left to a caller's wider wrapper: a
 * `hx-boost` on anything broader than the pagination controls themselves would also catch
 * unrelated same-origin links inside that wrapper (a Moment card's own stretched link to its
 * discussion page, say), boosting navigations this component knows nothing about into a swap
 * that makes no sense for them.
 */
class Pagination extends Component
{
    public LengthAwarePaginator $paginator;

    public function __construct(public Context $context, public ?string $target = null)
    {
        $this->paginator = $context->getSubjectPaginator();
    }

    public function render(): View
    {
        return view('kopling-core::page.pagination');
    }
}
