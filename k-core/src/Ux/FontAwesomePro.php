<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use BladeUI\Icons\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Kopling\Core\Settings\Settings;
use OwenVoke\BladeFontAwesome\Actions\CompileSvgsAction;

/**
 * Font Awesome Pro, downloaded per site with the site owner's own package token -- never shipped with Kopling.
 */
class FontAwesomePro
{
    public const REGISTRY = 'https://npm.fontawesome.com/@fortawesome%2Ffontawesome-pro';

    public const MAJOR = 7;

    public const REQUIRED_STYLES = ['solid', 'regular', 'brands'];

    protected const TOKEN_SETTING = 'kopling-core::fontawesome-pro-token';

    public static function path(?string $style = null): string
    {
        return storage_path('app/kopling/fontawesome-pro'.($style === null ? '' : '/'.$style));
    }

    public static function installed(): bool
    {
        return is_dir(self::path('solid'));
    }

    /**
     * @return array<int, string>
     */
    public static function installedStyles(): array
    {
        return array_values(array_filter(self::availableStyles(), fn (string $style) => is_dir(self::path($style))));
    }

    /**
     * @return array<int, string> styles blade-fontawesome has a prefix for, kits excluded
     */
    public static function availableStyles(): array
    {
        return array_values(array_diff(array_keys(config('blade-fontawesome', [])), ['custom-icons']));
    }

    /**
     * The free sets only take a Pro path through config: `Factory::add()` refuses a second set with the same prefix.
     */
    public static function boot(Application $app): void
    {
        $app->booting(fn () => self::configure($app['config']));
        $app->afterResolving(Factory::class, fn (Factory $factory) => self::register($factory, $app['config']));
    }

    public static function configure(Repository $config): void
    {
        foreach (array_intersect(self::installedStyles(), self::REQUIRED_STYLES) as $style) {
            $config->set("blade-fontawesome.{$style}.path", self::path($style));
        }
    }

    public static function register(Factory $factory, Repository $config): void
    {
        foreach (array_diff(self::installedStyles(), self::REQUIRED_STYLES) as $style) {
            $factory->add("fontawesome-{$style}", ['path' => self::path($style)] + $config->get("blade-fontawesome.{$style}", []));
        }
    }

    public static function token(): ?string
    {
        $stored = Settings::get(self::TOKEN_SETTING);

        try {
            return is_string($stored) ? Crypt::decryptString($stored) : null;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * @param array<int, string> $styles
     * @return string the installed version
     */
    public static function install(string $token, array $styles): string
    {
        $styles = array_values(array_unique([...self::REQUIRED_STYLES, ...$styles]));

        $package = Http::withToken($token)->acceptJson()->get(self::REGISTRY);
        if ($package->status() === 401 || $package->status() === 403) {
            throw new \RuntimeException('Font Awesome rejected the package token.');
        }
        $package->throw();

        [$version, $dist] = self::latest($package->json('versions', []));
        if (parse_url($dist['tarball'], PHP_URL_HOST) !== parse_url(self::REGISTRY, PHP_URL_HOST)) {
            throw new \RuntimeException('Refusing to send the package token to '.$dist['tarball'].'.');
        }

        $download = tempnam(sys_get_temp_dir(), 'fa-pro');
        $archive = $download.'.tar.gz';
        rename($download, $archive);

        try {
            Http::withToken($token)->sink($archive)->get($dist['tarball'])->throw();

            if (isset($dist['integrity']) && $dist['integrity'] !== 'sha512-'.base64_encode(hash_file('sha512', $archive, true))) {
                throw new \RuntimeException('The downloaded Font Awesome Pro package failed its integrity check.');
            }

            $staging = self::path().'.staging';
            File::deleteDirectory($staging);

            foreach ($styles as $style) {
                $source = 'phar://'.$archive.'/package/svgs/'.$style;
                if (! is_dir($source)) {
                    throw new \RuntimeException("Font Awesome Pro {$version} has no [{$style}] style.");
                }
                File::ensureDirectoryExists("{$staging}/{$style}");
                (new CompileSvgsAction($source, "{$staging}/{$style}"))->execute();
            }

            File::deleteDirectory(self::path());
            File::moveDirectory($staging, self::path());
        } finally {
            @unlink($archive);
        }

        Settings::set(self::TOKEN_SETTING, Crypt::encryptString($token));

        return $version;
    }

    public static function uninstall(): void
    {
        File::deleteDirectory(self::path());
        Settings::forget(self::TOKEN_SETTING);
    }

    /**
     * @param array<string, array{dist?: array{tarball: string, integrity?: string}}> $versions
     * @return array{0: string, 1: array{tarball: string, integrity?: string}}
     */
    protected static function latest(array $versions): array
    {
        $stable = array_filter(
            array_keys($versions),
            fn (string $version) => preg_match('/^'.self::MAJOR.'\.\d+\.\d+$/', $version) === 1 && isset($versions[$version]['dist']['tarball']),
        );

        if ($stable === []) {
            throw new \RuntimeException('No Font Awesome Pro '.self::MAJOR.'.x release is available for this token.');
        }

        usort($stable, 'version_compare');
        $version = end($stable);

        return [$version, $versions[$version]['dist']];
    }
}
