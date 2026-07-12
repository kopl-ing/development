<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Ux\Theme;

/**
 * The community topbar's theme picker: a dropdown of every installed theme, posting the
 * choice to ThemeController which stores it in the `kopling_theme` cookie. Server-rendered
 * and no-JS (daisyUI's focus-driven dropdown + a plain form per option) -- the page simply
 * re-renders with the newly-active theme's tokens, on-charter with "server is the single
 * source of truth". Registers itself into `kopling-core::community.topbar` the same way each
 * Card component declares its own defaults, so Core::ux() stays a thin composition point.
 */
class ThemeSwitcher extends Component
{
    public array $themes;

    public ?string $active;

    public function __construct(public array $data = [])
    {
        $this->themes = Theme::available();
        $this->active = Theme::active();
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(self::class)
            ->in('kopling-core::community.topbar')
            ->as('theme-switcher');
    }

    public function render(): View
    {
        return view('kopling-core::community.theme-switcher');
    }
}
