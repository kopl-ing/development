<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

/**
 * Lets an extension contribute extra validation rules (and optional custom messages) for a
 * model it doesn't own -- e.g. `reactions` adding `upvote_emoji`/`downvote_emoji` rules for
 * `Kopling\Tags\Tag` without `tags` ever needing to know `reactions` exists. Aggregated by
 * `Manager::modelValidationRules()`, keyed by the target model's fully-qualified class name;
 * the owning controller merges the result into whatever base rules it declares for its own
 * model's core fields, then validates once.
 *
 * Rules must be plain Laravel rule syntax (pipe-delimited strings or arrays of strings) --
 * never a closure or a `Rule::` object -- so the aggregated result stays cacheable the same way
 * every other `Manager` aggregation is (`RegistrationCache` flattens to a plain PHP array via
 * `var_export()`, which can represent neither). A rule genuinely needing the current model
 * instance (a `unique()` ignoring self, say) stays with whichever controller already has it in
 * scope, not declared here.
 */
interface ValidatesModels
{
    /**
     * @return array<class-string, array{rules: array<string, array<int, string>|string>, messages?: array<string, string>}>
     */
    public function modelValidationRules(): array;
}
