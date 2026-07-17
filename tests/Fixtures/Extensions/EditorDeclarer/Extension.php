<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\EditorDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesEditor;
use Kopling\Core\Ux\Editor\EditorNode;

class Extension extends AbstractExtension implements ChangesEditor
{
    public static function name(): string
    {
        return 'Editor Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Votes to enable TaskList (off by Core\'s own default), for testing ChangesEditor.';
    }

    /**
     * @return array<EditorNode>
     */
    public function editor(): array
    {
        return [EditorNode::TaskList];
    }
}
