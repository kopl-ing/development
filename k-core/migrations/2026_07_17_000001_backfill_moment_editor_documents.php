<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Core\Ux\Editor\EditorNode;

return new class extends Migration
{
    /**
     * One-time backfill for every Moment created before `body` became a ProseMirror JSON
     * document -- wraps its old plain text into a minimal single-paragraph document (so `body`
     * is valid ProseMirror JSON for every row from here on), then renders `body_html` for it
     * the same way `ComposerController::store()` does for new rows. Every `EditorNode` case is
     * treated as enabled here regardless of what's actually configured live -- this is a
     * one-time, trusted data migration over content `DocumentRenderer` already knows is safe
     * (plain text wrapped into a single paragraph has no marks/node types to gate at all).
     */
    public function up(): void
    {
        $enabled = EditorNode::cases();

        DB::table('moments')->orderBy('id')->chunkById(200, function ($rows) use ($enabled) {
            foreach ($rows as $row) {
                if (json_decode((string) $row->body) !== null) {
                    continue; // already a JSON document -- nothing to backfill
                }

                $body = json_encode([
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => $row->body !== '' ? [['type' => 'text', 'text' => (string) $row->body]] : [],
                    ]],
                ]);

                DB::table('moments')->where('id', $row->id)->update([
                    'body' => $body,
                    'body_html' => DocumentRenderer::render($body, $enabled),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible -- the original plain text isn't recoverable from wrapped JSON in a
        // way worth writing a lossy inverse for, for a one-time backfill.
    }
};
