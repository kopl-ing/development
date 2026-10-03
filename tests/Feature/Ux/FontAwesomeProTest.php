<?php

declare(strict_types=1);

use BladeUI\Icons\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Kopling\Core\Settings\Settings;
use Kopling\Core\Ux\FontAwesomePro;

function fontAwesomeProTarball(string $dir): string
{
    $tar = new PharData("{$dir}/fontawesome-pro.tar");
    foreach (['solid', 'regular', 'brands', 'light'] as $style) {
        $tar->addFromString("package/svgs/{$style}/star.svg", "<svg xmlns=\"http://www.w3.org/2000/svg\" height=\"1em\" viewBox=\"0 0 1 1\"><path d=\"{$style}\"/></svg>");
    }
    $tar->compress(Phar::GZ);

    return (string) file_get_contents("{$dir}/fontawesome-pro.tar.gz");
}

beforeEach(function () {
    $this->storage = sys_get_temp_dir().'/kopling-fa-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->storage);
    $this->app->useStoragePath($this->storage);

    $tarball = fontAwesomeProTarball($this->storage);
    $this->tarballUrl = 'https://npm.fontawesome.com/@fortawesome/fontawesome-pro/-/fontawesome-pro-7.1.0.tgz';
    $this->dist = ['tarball' => $this->tarballUrl, 'integrity' => 'sha512-'.base64_encode(hash('sha512', $tarball, true))];

    $this->versions = [
        '6.7.2' => ['dist' => ['tarball' => 'https://npm.fontawesome.com/old.tgz']],
        '7.0.1' => ['dist' => ['tarball' => 'https://npm.fontawesome.com/7.0.1.tgz']],
        '7.1.0' => ['dist' => $this->dist],
        '7.2.0-beta.1' => ['dist' => ['tarball' => 'https://npm.fontawesome.com/beta.tgz']],
    ];

    Http::fake([
        FontAwesomePro::REGISTRY => fn (Request $request) => $request->hasHeader('Authorization', 'Bearer good-token')
            ? Http::response(['versions' => $this->versions])
            : Http::response(['error' => 'unauthorized'], 401),
        $this->tarballUrl => fn () => Http::response($tarball),
    ]);
});

afterEach(fn () => File::deleteDirectory($this->storage));

it('downloads the latest 7.x Pro release with the site owner\'s token and stores the token encrypted', function () {
    $this->artisan('kopling:icons:pro', ['--token' => 'good-token', '--styles' => 'light'])
        ->expectsOutputToContain('Font Awesome Pro 7.1.0 installed: brands, regular, solid, light.')
        ->assertSuccessful();

    expect(file_get_contents(FontAwesomePro::path('light').'/star.svg'))->toContain('<svg fill="currentColor" xmlns')->not->toContain('height="1em"')
        ->and(FontAwesomePro::token())->toBe('good-token')
        ->and(Settings::get('kopling-core::fontawesome-pro-token'))->not->toContain('good-token');

    FontAwesomePro::configure(config());
    $this->app->forgetInstance(Factory::class);

    expect(svg('fas-star')->toHtml())->toContain('d="solid"')
        ->and(svg('fal-star')->toHtml())->toContain('d="light"');
});

it('reuses the stored token and installed styles on a re-run, and falls back to Free once disabled', function () {
    $this->artisan('kopling:icons:pro', ['--token' => 'good-token', '--styles' => 'light'])->assertSuccessful();
    $this->artisan('kopling:icons:pro')->expectsOutputToContain('brands, regular, solid, light.')->assertSuccessful();

    $this->artisan('kopling:icons:pro', ['--disable' => true])->assertSuccessful();

    expect(is_dir(FontAwesomePro::path()))->toBeFalse()
        ->and(FontAwesomePro::token())->toBeNull();
});

it('refuses a rejected token, an unknown style, a tampered download, and a tarball on another host', function () {
    $this->artisan('kopling:icons:pro', ['--token' => 'bad-token'])->expectsOutputToContain('rejected the package token')->assertFailed();
    $this->artisan('kopling:icons:pro', ['--token' => 'good-token', '--styles' => 'neon'])->expectsOutputToContain('Unknown styles: neon')->assertFailed();

    $this->versions = ['7.1.0' => ['dist' => ['tarball' => $this->tarballUrl, 'integrity' => 'sha512-tampered']]];
    $this->artisan('kopling:icons:pro', ['--token' => 'good-token'])->expectsOutputToContain('integrity check')->assertFailed();

    $this->versions = ['7.1.0' => ['dist' => ['tarball' => 'https://evil.example/pro.tgz']]];
    $this->artisan('kopling:icons:pro', ['--token' => 'good-token'])->expectsOutputToContain('Refusing to send the package token')->assertFailed();

    expect(FontAwesomePro::installed())->toBeFalse()
        ->and(FontAwesomePro::token())->toBeNull();
});
