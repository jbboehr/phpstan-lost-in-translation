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

final class ShouldNotHappenException extends \RuntimeException
{
    private const URL = 'https://github.com/jbboehr/phpstan-lost-in-translation/issues';

    private static ?string $url = null;

    private static string $composerJsonPath = __DIR__ . '/../composer.json';

    /**
     * @throws self
     */
    public static function rethrow(\Throwable $e): never
    {
        throw new self($e->getMessage(), $e);
    }

    public function __construct(
        string $message = 'Internal error',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('%s, please open an issue on GitHub %s', $message, self::getUrl()),
            0,
            $previous
        );
    }

    private static function getUrl(): string
    {
        if (null !== self::$url) {
            return self::$url;
        }

        try {
            $raw = file_exists(self::$composerJsonPath) ? file_get_contents(self::$composerJsonPath) : false;

            if (false === $raw) {
                return self::$url = self::URL;
            }

            $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($raw)) {
                return self::$url = self::URL;
            }

            $name = $raw['name'] ?? '';
            $url = $raw['homepage'] ?? self::URL;

            if (!is_string($name) || !is_string($url)) {
                return self::$url = self::URL;
            }

            if (!str_contains($name, 'lost-in-translation')) {
                error_log("Auto-detecting root package name produced unusual name: " . $name);
            }

            return self::$url = $url;
        } catch (\JsonException) {
            return self::$url = self::URL;
        } catch (\Throwable $e) {
            error_log((string) $e);

            return self::$url = self::URL;
        }
    }
}
