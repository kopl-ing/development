<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Kopling\Core\Extension\Manifest;

function localManifest(array $composer, array $installed = []): array
{
    $files = new Filesystem();
    $base = sys_get_temp_dir().'/kopling-manifest-'.uniqid();

    $files->ensureDirectoryExists($base.'/vendor/composer');
    $files->ensureDirectoryExists($base.'/vendor/acme/teams');
    $files->ensureDirectoryExists($base.'/extensions/teams/src');
    $files->put($base.'/composer.json', json_encode($composer));
    $files->put($base.'/vendor/composer/installed.json', json_encode(['packages' => $installed]));

    $manifest = new Manifest($files, $base, $base.'/manifest.php');
    $manifest->vendorPath = $base.'/vendor';
    $manifest->build();

    $extensions = $manifest->extensions();
    $files->deleteDirectory($base);

    return [$extensions];
}

it('discovers an extension declared in the app composer.json', function () {
    [$extensions] = localManifest([
        'autoload' => ['psr-4' => ['App\\' => 'app/', 'Acme\\Teams\\' => 'extensions/teams/src/']],
        'extra' => ['kopling' => ['extensions' => ['acme/teams' => 'extensions/teams']]],
    ]);

    expect(array_keys($extensions))->toBe(['acme/teams'])
        ->and($extensions['acme/teams']['namespace'])->toBe('Acme\\Teams\\')
        ->and($extensions['acme/teams']['path'])->toEndWith('/extensions/teams');
});

it('lets a local extension replace an installed package of the same name', function () {
    [$extensions] = localManifest(
        [
            'autoload' => ['psr-4' => ['Acme\\Teams\\' => 'extensions/teams/src/']],
            'extra' => ['kopling' => ['extensions' => ['acme/teams' => 'extensions/teams']]],
        ],
        [['name' => 'acme/teams', 'type' => 'kopling-extension', 'autoload' => ['psr-4' => ['Acme\\Teams\\' => 'src/']]]],
    );

    expect($extensions)->toHaveCount(1)
        ->and($extensions['acme/teams']['path'])->toEndWith('/extensions/teams');
});

it('skips a declared extension whose directory does not exist', function () {
    [$extensions] = localManifest([
        'autoload' => ['psr-4' => ['Acme\\Missing\\' => 'extensions/missing/src/']],
        'extra' => ['kopling' => ['extensions' => ['acme/missing' => 'extensions/missing']]],
    ]);

    expect($extensions)->toBe([]);
});
