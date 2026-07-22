<?php

declare(strict_types=1);

namespace Kopling\Docs;

use Illuminate\Support\Collection;
use League\CommonMark\CommonMarkConverter;
use Kopling\Core\Storage\Resolver;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/**
 * The one class that ever touches CommonMark/YamlFrontMatter directly -- routes/controllers
 * read `DocPage` rows only, same "mainstream tool inside, sovereign contract outside" discipline
 * `Ux\Editor` already applies to Tiptap. The drive (`Resolver::resolve('kopling-docs::content')`)
 * stays the authored source of truth; `docs_pages` is only a queryable/cacheable index over it,
 * rebuilt by explicitly running `kopling:docs:sync` -- same non-magic, explicit-refresh
 * convention as `kopling:extensions:cache`/`RegistrationCache`, never a per-request filesystem
 * walk in production.
 */
class PageRegistry
{
    public function __construct(protected Resolver $resolver)
    {
    }

    /**
     * Skips re-rendering a file whose content hasn't changed since the last sync (compared by
     * `sha1`), and removes any `DocPage` whose file no longer exists on the drive -- an empty
     * drive correctly clears the whole index, not a no-op.
     *
     * @return int the number of pages written or updated
     */
    public function sync(): int
    {
        $disk = $this->resolver->resolve('kopling-docs::content');
        $converter = new CommonMarkConverter();

        $seenPaths = [];
        $written = 0;

        foreach ($disk->allFiles() as $path) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }

            $seenPaths[] = $path;

            $raw = (string) $disk->get($path);
            $hash = sha1($raw);

            $existing = DocPage::where('storage_path', $path)->first();

            if ($existing !== null && $existing->content_hash === $hash) {
                continue;
            }

            $document = YamlFrontMatter::parse($raw);
            $matter = $document->matter();
            $slug = $matter['slug'] ?? $this->slugFromPath($path);

            DocPage::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $matter['title'] ?? $slug,
                    'section' => $matter['section'] ?? 'General',
                    'order' => (int) ($matter['order'] ?? 0),
                    'locale' => $matter['locale'] ?? 'en',
                    'storage_path' => $path,
                    'content_hash' => $hash,
                    'content_html' => (string) $converter->convert($document->body()),
                ],
            );

            $written++;
        }

        DocPage::whereNotIn('storage_path', $seenPaths)->delete();

        return $written;
    }

    /**
     * @return Collection<string, Collection<int, DocPage>>
     */
    public function tree(): Collection
    {
        return DocPage::orderBy('order')->orderBy('title')->get()->groupBy('section');
    }

    protected function slugFromPath(string $path): string
    {
        return trim((string) preg_replace('/\.md$/', '', $path), '/');
    }
}
