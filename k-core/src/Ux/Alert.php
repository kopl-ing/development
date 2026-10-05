<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Kopling\Core\People\Person;
use Kopling\Core\Settings\PersonSettings;

/**
 * The signed-in person's device-alert preferences, read by `kopling.alert()` and the wake lock in `app.js`.
 */
class Alert
{
    public const VIBRATE = 'kopling-core::alert-vibrate';

    public const SOUND = 'kopling-core::alert-sound';

    public const WAKE_LOCK = 'kopling-core::wake-lock';

    /** Must match the sound names `resources/js/alert.js` can synthesize. */
    public const SOUNDS = ['none', 'beep', 'chime', 'whistle'];

    public const DEFAULT_SOUND = 'beep';

    /**
     * @return array<string, string>
     */
    public static function soundOptions(): array
    {
        return collect(self::SOUNDS)
            ->mapWithKeys(fn (string $sound) => [$sound => __("kopling-core::settings.sound_{$sound}")])
            ->all();
    }

    /**
     * @return array{vibrate: bool, sound: string, wakeLock: bool}
     */
    public static function preferences(?Person $person): array
    {
        $stored = $person ? PersonSettings::all($person) : [];
        $sound = $stored[self::SOUND] ?? self::DEFAULT_SOUND;

        return [
            'vibrate' => ($stored[self::VIBRATE] ?? '1') !== '0',
            'sound' => in_array($sound, self::SOUNDS, true) ? $sound : self::DEFAULT_SOUND,
            'wakeLock' => ($stored[self::WAKE_LOCK] ?? '1') !== '0',
        ];
    }
}
