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
use PHPStan\Type\VerbosityLevel;

/**
 * @internal
 */
final class Utils
{
    private static string $applicationClass = Application::class;

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

    public static function detectLangPath(): string
    {
        $applicationClass = self::$applicationClass;

        if (!class_exists($applicationClass)) {
            return 'lang';
        }

        $app = $applicationClass::getInstance();

        if (!($app instanceof Application) || !$app->isBooted()) {
            return 'lang';
        }

        return $app->langPath();
    }

    public static function e(string $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('JsonException: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
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
