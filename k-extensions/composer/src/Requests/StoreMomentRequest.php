<?php

declare(strict_types=1);

namespace Kopling\Composer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;

/**
 * The first `FormRequest` in this codebase -- validation was previously ad hoc `trim()`/
 * `abort_if()` in the controller, which was fine for a plain `<textarea>` but can't express
 * "well-formed ProseMirror JSON, only using currently-enabled node/mark types" the way this
 * needs to now that `body` is a TipTap document rather than plain text.
 *
 * Also merges in whatever `ValidatesModels::modelValidationRules()` any extension contributed
 * for `Moment::class` -- e.g. `tags`' own min/max-selected-tags rule for the picker it
 * registers into `kopling-composer::compose.fields` (see that extension's `Extension::ux()`).
 * Composer never names `tags` or any other contributor here, same split `TagsController`'s own
 * merge already established.
 */
class StoreMomentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route this backs already gates with the 'auth' middleware (see
        // k-extensions/composer/routes/web.php) -- nothing further to check here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Manager $manager): array
    {
        return $manager->mergeModelValidationRules(Moment::class, [
            // `moments.title` is `NOT NULL` at the schema level -- required here to match,
            // rather than the DB staying stricter than what the form actually enforces.
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', new ValidDocument($manager->editorNodes())],
        ])['rules'];
    }

    /**
     * Not container-resolved the way `rules()` is -- `FormRequest::createDefaultValidator()`
     * calls this as a plain `$this->messages()`, not through `Container::call()` -- so `Manager`
     * is resolved directly rather than type-hinted as a parameter here. No base messages of its
     * own to merge in, so the second argument is left at its default.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(Manager::class)->mergeModelValidationRules(Moment::class, [])['messages'];
    }
}
