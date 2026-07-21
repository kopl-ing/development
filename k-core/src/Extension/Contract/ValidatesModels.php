<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

/**
 * Lets an extension contribute extra validation rules (and messages) for a model it doesn't
 * own -- e.g. `reactions` adding rules for `Tag` without `tags` knowing `reactions` exists.
 * Rules must be plain Laravel rule syntax, never a closure or `Rule::` object, so the aggregated
 * result stays `RegistrationCache`-cacheable (`var_export()` can't represent either).
 */
interface ValidatesModels
{
    /**
     * @return array<class-string, array{rules: array<string, array<int, string>|string>, messages?: array<string, string>}>
     */
    public function modelValidationRules(): array;
}
