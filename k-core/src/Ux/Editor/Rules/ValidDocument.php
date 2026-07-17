<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Editor\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Core\Ux\Editor\EditorNode;
use Kopling\Core\Ux\Editor\PlainTextExtractor;

/**
 * A submitted `body` is a well-formed, size/depth-bounded ProseMirror document using only
 * currently-enabled `EditorNode` types, and isn't empty of actual text -- shared by every
 * `FormRequest` that accepts editor content (`StoreMomentRequest`, `StoreReplyRequest`), so the
 * same check can't drift between them the way two hand-written duplicate closures could.
 */
class ValidDocument implements ValidationRule
{
    /**
     * @param  array<EditorNode>  $enabled
     */
    public function __construct(protected array $enabled)
    {
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        try {
            DocumentRenderer::validate((string) $value, $this->enabled);
        } catch (\InvalidArgumentException $e) {
            $fail($e->getMessage());

            return;
        }

        if (trim(PlainTextExtractor::extract((string) $value)) === '') {
            $fail('The :attribute must not be empty.');
        }
    }
}
