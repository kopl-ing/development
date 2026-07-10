<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The outer shell of a content card -- a discussion preview today, whatever else wants this
 * shape later. Owns the daisyUI `card`/`card-body` structure; `Top`/`Body`/`Footer` are
 * plain content inside that one `card-body` (daisyUI itself has no separate "card header"
 * part), each free to be used, reordered, or left out. Not itself replaceable/extensible in
 * this pass -- only what's inside its header/body/footer is (see those classes' own
 * `SLOT`/`defaults()`).
 */
class Card extends Component
{
    public function __construct(public Context $context)
    {
    }

    public function render(): View
    {
        return view('core::card.card');
    }
}
