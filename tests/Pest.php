<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Support\FakeManifest;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A `Manager` wired to a `FakeManifest` instead of the real one -- entirely standalone, no
 * Laravel app booted -- so extensibility-mechanism tests (portals/portalExtensions/permissions/
 * ux/models) can control exactly which extensions exist without a real Composer package per
 * fixture. `Manager` always prepends real `Core` regardless (see `Manager::extensions()`), so
 * assertions should check for a fixture's own entries rather than asserting an exact, exhaustive
 * set.
 *
 * @param  array<string, array{namespace: string, path: string}>  $extensions
 */
function fakeManager(array $extensions = []): Manager
{
    // A path that never exists, so every aggregation always computes live from the fixtures --
    // a fakeManager() test must never be able to see a real, previously-written registration
    // cache from bootstrap/cache/kopling-registrations.php.
    $cache = new RegistrationCache(sys_get_temp_dir().'/kopling-test-registrations-does-not-exist.php');

    return new Manager(new FakeManifest($extensions), new Dispatcher(), $cache);
}

/**
 * A ProseMirror/TipTap JSON document string, for `DocumentRenderer`/`PlainTextExtractor`/
 * `ValidDocument` tests -- shared here rather than duplicated per test file since both
 * DocumentRendererTest and PlainTextExtractorTest need the exact same document shapes.
 *
 * @param  array<int, array>  $content
 */
function editorDoc(array $content): string
{
    return json_encode(['type' => 'doc', 'content' => $content]);
}

/**
 * @param  array<int, array>  $marks
 */
function editorText(string $text, array $marks = []): array
{
    return array_filter([
        'type' => 'text',
        'text' => $text,
        'marks' => $marks,
    ], fn ($value) => $value !== []);
}

/**
 * Labels of every entry in the community topbar's own user-menu dropdown (`UserMenu::SLOT`),
 * scoped to that dropdown alone -- not the whole page -- so a check like "Admin panel isn't
 * shown" can't accidentally pass because that same text happens to appear elsewhere (a page
 * heading, a showcase demo). The trigger `<button>`'s `aria-label` ("Account menu") is unique;
 * its `popovertarget` names the sibling `<ul>` `Dropdown` renders entries into (`Dropdown::$id`
 * is random per instance, so multiple dropdowns on one page never collide) -- shared across
 * AdminLinkInUserMenuTest/StyleGuideLinkInUserMenuTest/StyleGuideTopbarUserMenuTest/
 * UserMenuHideOnPortalTest rather than duplicated in each.
 *
 * @return array<int, string>
 */
function userMenuLabels(string $html): array
{
    $crawler = new Crawler($html);
    $triggerLabel = __('kopling-core::community.account_menu');
    $id = $crawler->filter('button[aria-label="'.$triggerLabel.'"]')->attr('popovertarget');

    return $crawler->filter('#'.$id.' a')->each(fn (Crawler $a) => trim($a->text()));
}

/**
 * A minimal `PageSectionTemplate` -- a "title" (string) slot and a "content" (wysiwyg) slot --
 * shared across Pages' controller/renderer tests so they don't each hand-roll the same template
 * shape.
 *
 * @param  array<string, mixed>  $overrides
 */
function pageSectionTemplate(array $overrides = []): \Kopling\Pages\PageSectionTemplate
{
    return \Kopling\Pages\PageSectionTemplate::create([
        'name' => 'Test template',
        'blade_source' => '<div>{{ $title }}{!! $content !!}</div>',
        'slots' => [
            ['name' => 'title', 'type' => 'string', 'label' => 'Title'],
            ['name' => 'content', 'type' => 'wysiwyg', 'label' => 'Content'],
        ],
        ...$overrides,
    ]);
}
