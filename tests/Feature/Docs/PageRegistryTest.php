<?php

declare(strict_types=1);

use Kopling\Core\Storage\Drive;
use Kopling\Core\Storage\StorageMapping;
use Kopling\Docs\DocPage;
use Kopling\Docs\PageRegistry;

/*
 * RefreshDatabase rolls back the DB after every test, so the mapped Drive/StorageMapping must be
 * created fresh in beforeEach() rather than memoized across tests -- a plain static-variable
 * memoization would keep returning a temp directory whose backing DB rows no longer exist once
 * the first test's transaction rolls back.
 */
beforeEach(function () {
    $this->docsRoot = sys_get_temp_dir().'/kopling-docs-test-'.uniqid();
    mkdir($this->docsRoot, 0755, true);
});

function mapDocsDrive(string $root): void
{
    StorageMapping::create([
        'request_id' => 'kopling-docs::content',
        'drive_id' => Drive::create(['name' => 'Docs Test Drive', 'driver' => 'local', 'settings' => ['root' => $root]])->id,
    ]);
}

function writeDocFile(string $root, string $relativePath, string $frontMatterAndBody): void
{
    $full = $root.'/'.$relativePath;
    @mkdir(dirname($full), 0755, true);
    file_put_contents($full, $frontMatterAndBody);
}

it('throws the same way Resolver does when nothing is mapped yet', function () {
    expect(fn () => app(PageRegistry::class)->sync())
        ->toThrow(RuntimeException::class, 'is not mapped to an enabled drive');
});

it('parses front matter and renders the Markdown body into content_html', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'getting-started.md', <<<'MD'
        ---
        title: Getting Started
        section: Basics
        order: 1
        ---
        # Hello

        Some **bold** text.
        MD);

    $written = app(PageRegistry::class)->sync();

    expect($written)->toBe(1);

    $page = DocPage::sole();
    expect($page->slug)->toBe('getting-started')
        ->and($page->title)->toBe('Getting Started')
        ->and($page->section)->toBe('Basics')
        ->and($page->order)->toBe(1)
        ->and($page->content_html)->toContain('<h1>Hello</h1>')
        ->and($page->content_html)->toContain('<strong>bold</strong>');
});

it('derives a hierarchical slug from the file path when front matter has no slug override', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'extending/portals.md', "---\ntitle: Portals\nsection: Extending\n---\nBody.");

    app(PageRegistry::class)->sync();

    expect(DocPage::where('slug', 'extending/portals')->exists())->toBeTrue();
});

it('respects an explicit slug in front matter over the derived path', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'renamed-file.md', "---\ntitle: Custom\nsection: General\nslug: custom-slug\n---\nBody.");

    app(PageRegistry::class)->sync();

    expect(DocPage::where('slug', 'custom-slug')->exists())->toBeTrue()
        ->and(DocPage::where('slug', 'renamed-file')->exists())->toBeFalse();
});

it('skips re-rendering a file whose content has not changed since the last sync', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'stable.md', "---\ntitle: Stable\nsection: General\n---\nOriginal.");
    app(PageRegistry::class)->sync();

    $before = DocPage::where('slug', 'stable')->sole();
    $beforeUpdatedAt = $before->updated_at;

    sleep(1);
    app(PageRegistry::class)->sync();

    expect(DocPage::where('slug', 'stable')->sole()->updated_at->equalTo($beforeUpdatedAt))->toBeTrue();
});

it('re-renders a file once its content actually changes', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'changing.md', "---\ntitle: Changing\nsection: General\n---\nOriginal.");
    app(PageRegistry::class)->sync();

    writeDocFile($this->docsRoot, 'changing.md', "---\ntitle: Changing\nsection: General\n---\nUpdated.");
    app(PageRegistry::class)->sync();

    expect(DocPage::where('slug', 'changing')->sole()->content_html)->toContain('Updated');
});

it('removes a DocPage whose file no longer exists on the drive', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'temporary.md', "---\ntitle: Temporary\nsection: General\n---\nBody.");
    app(PageRegistry::class)->sync();

    expect(DocPage::where('slug', 'temporary')->exists())->toBeTrue();

    unlink($this->docsRoot.'/temporary.md');
    app(PageRegistry::class)->sync();

    expect(DocPage::where('slug', 'temporary')->exists())->toBeFalse();
});

it('builds tree() grouped by section, ordered', function () {
    mapDocsDrive($this->docsRoot);
    writeDocFile($this->docsRoot, 'a.md', "---\ntitle: A\nsection: Zeta\norder: 1\n---\nBody.");
    writeDocFile($this->docsRoot, 'b.md', "---\ntitle: B\nsection: Alpha\norder: 1\n---\nBody.");
    app(PageRegistry::class)->sync();

    $tree = app(PageRegistry::class)->tree();

    expect($tree->keys()->all())->toContain('Zeta', 'Alpha')
        ->and($tree->get('Alpha')->first()->title)->toBe('B');
});
