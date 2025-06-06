<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use Illuminate\Foundation\Application;
use Symfony\Component\Intl\Locales;

/**
 * @internal
 * @phpstan-import-type MetadataType from Identifier
 */
final class Utils
{
    /**
     * @param ?list<string> $missingInLocales
     * @phpstan-return MetadataType
     */
    public static function metadata(
        ?string $key = null,
        ?string $locale = null,
        ?string $value = null,
        ?array $missingInLocales = null,
    ): array {
        $metadata = [];

        if (null !== $key) {
            $metadata[Identifier::METADATA_KEY] = $key;
        }

        if (null !== $locale) {
            $metadata[Identifier::METADATA_LOCALE] = $locale;
        }

        if (null !== $value) {
            $metadata[Identifier::METADATA_VALUE] = $value;
        }

        if (null !== $missingInLocales) {
            $metadata[Identifier::METADATA_MISSING_IN_LOCALES] = $missingInLocales;
        }

        return $metadata;
    }

    public static function checkLocaleExists(string $locale, bool $strict = false): bool
    {
        if (!$strict) {
            // Allow specifying it with a dash instead of an underscore and with incorrect cases >.>
            $locale = str_replace('-', '_', $locale);
            if (str_contains($locale, '_')) {
                $parts = explode('_', $locale, 2);
                assert(count($parts) >= 2);
                $locale = strtolower($parts[0]) . '_' . strtoupper($parts[1]);
            } else {
                $locale = strtolower($locale);
            }
        }

        return Locales::exists($locale);
    }

    /**
     * @internal
     * @phpstan-param ?class-string<Application> $applicationClass
     */
    public static function detectLangPath(?string $applicationClass = Application::class): string
    {
        if (null === $applicationClass || !class_exists($applicationClass, false)) {
            return 'lang';
        }

        // I don't want to initialize the application if it's not already initialized...
        $r = new \ReflectionProperty($applicationClass, 'instance');
        $app = $r->getValue(null);

        if (!($app instanceof Application) || !$app->isBooted()) {
            return 'lang';
        }

        return $app->langPath();
    }

    /**
     * @internal
     * @phpstan-param ?class-string<Application> $applicationClass
     */
    public static function detectBaseLocale(?string $applicationClass = Application::class): string
    {
        if (null === $applicationClass || !class_exists($applicationClass, false)) {
            return 'en';
        }

        // I don't want to initialize the application if it's not already initialized...
        $r = new \ReflectionProperty($applicationClass, 'instance');
        $app = $r->getValue(null);

        if (!($app instanceof Application) || !$app->isBooted()) {
            return 'en';
        }

        return $app->currentLocale();
    }

    public static function e(string $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if (str_contains($exception->getMessage(), 'Malformed UTF-8 characters')) {
                return '"' . self::escapeBinary($value) . '"';
            }

            throw new \RuntimeException('JsonException: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public static function escapeBinary(string $value): string
    {
        $buf = '';

        for ($i = 0; $i < strlen($value); $i++) {
            $c = $value[$i];

            if ($c === '"') {
                $buf .= '\"';
            } elseif (ctype_print($c)) {
                $buf .= $c;
            } else {
                $buf .= sprintf("\x%02x", ord($c));
            }
        }

        return $buf;
    }

    public static function formatTipForKeyValue(string $locale, string $key, ?string $value = null): string
    {
        if (null === $value || $key === $value) {
            return sprintf("Locale: %s, Key: %s", self::e($locale), self::e($key));
        } else {
            return sprintf("Locale: %s, Key: %s, Value: %s", self::e($locale), self::e($key), self::e($value));
        }
    }
}
