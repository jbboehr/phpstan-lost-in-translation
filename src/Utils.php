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

use Brick\VarExporter\VarExporter;
use Illuminate\Foundation\Application;
use PHPStan\Type\VerbosityLevel;
use Symfony\Component\Intl\Locales;

/**
 * @internal
 */
final class Utils
{
    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    public static function callToMetadata(TranslationCall $call, array $extra = []): array
    {
        $metadata = [];
        $metadata['lit::key'] = $call->keyType->describe(VerbosityLevel::precise());

        if (null !== $call->replaceType) {
            $metadata['lit::replace'] = $call->replaceType->describe(VerbosityLevel::precise());
        }

        if (null !== $call->localeType) {
            $metadata['lit::locale'] = $call->localeType->describe(VerbosityLevel::precise());
        }

        return array_merge($metadata, $extra);
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
