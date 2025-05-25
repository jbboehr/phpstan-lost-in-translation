<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

final class ShouldNotHappenException extends \RuntimeException
{
    private const URL = 'https://github.com/jbboehr/phpstan-lost-in-translation/issues';

    private static ?string $url = null;

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
            $raw = file_get_contents(__DIR__ . '/../composer.json');

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
        } catch (\Throwable $e) {
            error_log((string) $e);

            return self::$url = self::URL;
        }
    }
}
