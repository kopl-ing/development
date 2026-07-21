<?php

declare(strict_types=1);

namespace Kopling\Composer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\PlainTextExtractor;
use Kopling\Core\Ux\Editor\Rules\ValidDocument;

class StoreMomentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The text editor stays mounted regardless of which compose mode is active (every mode's
     * panel stays in the DOM so switching tabs never discards a draft), so its hidden input
     * always submits *some* ProseMirror JSON -- a trivial empty-paragraph doc when untouched.
     * `ValidDocument`'s shared "must have real text" check has no way to tell that apart from a
     * genuine empty submission, so it fires even on a poll-only moment that never touched the
     * text tab. Normalized here, once, to null before validation ever sees it -- `nullable`
     * then skips `ValidDocument` entirely, the same way an absent `body` already would.
     */
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if ($body !== null && trim(PlainTextExtractor::extract((string) $body)) === '') {
            $this->merge(['body' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Manager $manager): array
    {
        $textMode = $this->input('compose_mode', 'kopling-composer::text') === 'kopling-composer::text';

        return $manager->mergeModelValidationRules(Moment::class, [
            'title' => ['required', 'string', 'max:150'],
            'body' => [Rule::requiredIf($textMode), 'nullable', 'string', new ValidDocument($manager->editorNodes())],
        ])['rules'];
    }

    /**
     * Called directly, not container-resolved like `rules()` -- `Manager` must be pulled with
     * `app()` here.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(Manager::class)->mergeModelValidationRules(Moment::class, [])['messages'];
    }
}
