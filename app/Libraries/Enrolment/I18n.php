<?php

declare(strict_types=1);

namespace App\Libraries\Enrolment;

/**
 * Receipt/enrolment content strings in the 13 languages a member can pick
 * at enrolment — ported verbatim from the Hithachintak prototype's I18N /
 * TRUST_NAME_I18N data (app/Libraries/Enrolment/data/*.json). This is
 * deliberately separate from CI4's own `app.defaultLocale` mechanism: the
 * admin UI stays English-only, while this picks the *member's* chosen
 * language for their enrolment form section labels and receipt.
 */
class I18n
{
    private static ?array $strings = null;
    private static ?array $trustNames = null;

    public const LANGUAGES = ['en', 'hi', 'kn', 'gu', 'te', 'ml', 'ta', 'mr', 'or', 'bn', 'pa', 'as', 'mni'];

    /** @return array<string, string> */
    public static function t(?string $lang): array
    {
        self::load();
        $lang ??= 'en';

        return self::$strings[$lang] ?? self::$strings['en'];
    }

    public static function trustName(?string $lang): string
    {
        self::load();
        $lang ??= 'en';

        return self::$trustNames[$lang] ?? self::$trustNames['en'];
    }

    /** @return array<string, string> code => native label, e.g. 'hi' => 'हिंदी' */
    public static function languageOptions(): array
    {
        self::load();
        $options = [];
        foreach (self::LANGUAGES as $code) {
            $options[$code] = self::$strings[$code]['native'] ?? self::$strings[$code]['label'];
        }

        return $options;
    }

    private static function load(): void
    {
        if (self::$strings !== null) {
            return;
        }

        self::$strings    = json_decode(file_get_contents(__DIR__ . '/data/i18n.json'), true, flags: JSON_THROW_ON_ERROR);
        self::$trustNames = json_decode(file_get_contents(__DIR__ . '/data/trust_name_i18n.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
