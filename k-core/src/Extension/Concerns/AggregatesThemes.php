<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\ColorScheme;
use Kopling\Core\Ux\Theme\Token;

trait AggregatesThemes
{
    /**
     * Validates each token against `Token`'s own catalog, throwing on an unrecognized key or a
     * value that doesn't match (a `ChangesTheme` implementor's own bug, not a dangling reference).
     *
     * @return Collection<string, array<string, string>>
     */
    public function themes(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['themes']);
        }

        $themes = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesTheme) {
                continue;
            }

            $declared = $extension->theme();

            foreach ($declared as $token => $value) {
                $case = Token::tryFrom($token);

                if ($case === null) {
                    throw new \InvalidArgumentException(
                        "[{$package}] declared an unrecognized theme token [{$token}]."
                    );
                }

                if (! $case->matches($value)) {
                    throw new \InvalidArgumentException(
                        "[{$package}]'s theme token [{$token}] has an invalid value [{$value}]."
                    );
                }
            }

            $themes[$this->id($package)] = $declared;
        }

        return collect($themes);
    }

    /**
     * @return Collection<string, ColorScheme>
     */
    public function themeColorSchemes(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['themeColorSchemes'])->map(
                fn (string $scheme) => ColorScheme::from($scheme)
            );
        }

        $schemes = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesTheme) {
                $schemes[$this->id($package)] = $extension->colorScheme();
            }
        }

        return collect($schemes);
    }

    /**
     * @return array<string, string>
     */
    public function themeChoices(): array
    {
        $choices = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesTheme) {
                $choices[$this->id($package)] = $extension::name();
            }
        }

        return $choices;
    }
}
