<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Ux\FontAwesomePro;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'kopling:icons:pro')]
class FontAwesomeProIcons extends Command
{
    protected $signature = 'kopling:icons:pro
        {--token= : Font Awesome Pro package token; defaults to the stored one}
        {--styles= : Extra styles besides solid, regular and brands, comma separated (light,thin,duotone,...)}
        {--disable : Remove the Pro icons and the stored token, back to Font Awesome Free}';

    protected $description = 'Download Font Awesome Pro icons with your own package token and use them sitewide';

    public function handle(): int
    {
        if ($this->option('disable')) {
            FontAwesomePro::uninstall();
            $this->callSilently('icons:clear');
            $this->components->info('Font Awesome Pro removed; icons fall back to Font Awesome Free.');

            return self::SUCCESS;
        }

        $token = $this->option('token') ?: FontAwesomePro::token() ?: $this->secret('Font Awesome Pro package token');
        if (! $token) {
            $this->components->error('A Font Awesome Pro package token is required.');

            return self::FAILURE;
        }

        $styles = $this->option('styles') !== null
            ? array_filter(array_map('trim', explode(',', $this->option('styles'))))
            : FontAwesomePro::installedStyles();
        if ($unknown = array_diff($styles, FontAwesomePro::availableStyles())) {
            $this->components->error('Unknown styles: '.implode(', ', $unknown).'. Available: '.implode(', ', FontAwesomePro::availableStyles()).'.');

            return self::FAILURE;
        }

        $this->components->info('Downloading Font Awesome Pro...');

        try {
            $version = FontAwesomePro::install($token, $styles);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->callSilently('icons:clear');
        $this->components->info("Font Awesome Pro {$version} installed: ".implode(', ', FontAwesomePro::installedStyles()).'.');

        return self::SUCCESS;
    }
}
