<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Core\Ux\Editor\EditorNode;
use Kopling\Discussions\Support\LegacyReplyDocument;

return new class extends Migration
{
    /**
     * One-time backfill for every Reply created before `body` became a ProseMirror JSON
     * document -- see `LegacyReplyDocument` for how a legacy plain-text body (including the old
     * "> Author: text" quote convention) is turned into one.
     */
    public function up(): void
    {
        $enabled = EditorNode::cases();

        DB::table('replies')->orderBy('id')->chunkById(200, function ($rows) use ($enabled) {
            foreach ($rows as $row) {
                if (json_decode((string) $row->body) !== null) {
                    continue; // already a JSON document -- nothing to backfill
                }

                $body = json_encode(LegacyReplyDocument::toDocument((string) $row->body));

                DB::table('replies')->where('id', $row->id)->update([
                    'body' => $body,
                    'body_html' => DocumentRenderer::render($body, $enabled),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible -- see moments' own backfill migration for the same reasoning.
    }
};
