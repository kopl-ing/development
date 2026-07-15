<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\LoadOrder;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\LoadOrder\Directive;
use Kopling\Core\Extension\LoadOrder\InfluencesLoadOrder;
use Kopling\Core\Extension\LoadOrder\LoadsAfter;
use Kopling\Core\Extension\LoadOrder\LoadsBefore;

/**
 * One flexible fixture covering every `LoadOrder\Resolver` scenario -- explicit `LoadsAfter`/
 * `LoadsBefore` constraints, contract-dispatched `InfluencesLoadOrder` rules, and (since it
 * always implements `SomeContract` too) being a valid target for another instance's rules --
 * constructed directly in each test rather than discovered via `Manifest`, so a configurable
 * constructor is fine here (unlike the other fixtures, which `Manager::extensions()` always
 * instantiates with `new $class()`, no arguments). Implements both `LoadsAfter` and
 * `LoadsBefore` (unlike most real extensions, which only need one) specifically so a single
 * fixture class can exercise every direction `Resolver` supports.
 */
class ConfigurableExtension extends AbstractExtension implements LoadsAfter, LoadsBefore, InfluencesLoadOrder, SomeContract
{
    /**
     * @param  array<string>  $after  Composer package names this must load after.
     * @param  array<string>  $before  Composer package names this must load before.
     * @param  array<class-string, Directive>  $rules  loadOrderRules() -- contract => Directive.
     */
    public function __construct(
        protected array $after = [],
        protected array $before = [],
        protected array $rules = [],
    ) {
    }

    public static function name(): string
    {
        return 'Configurable Load Order Fixture';
    }

    public static function description(): string
    {
        return 'Configurable LoadsAfter/LoadsBefore/InfluencesLoadOrder fixture, for testing Resolver directly.';
    }

    /**
     * @return array<string>
     */
    public function loadAfter(): array
    {
        return $this->after;
    }

    /**
     * @return array<string>
     */
    public function loadBefore(): array
    {
        return $this->before;
    }

    /**
     * @return array<class-string, Directive>
     */
    public function loadOrderRules(): array
    {
        return $this->rules;
    }
}
