<?php
declare(strict_types=1);

namespace Mfn\PHPStanLostInTranslation\Tests\TranslationLoader;

use Mfn\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PHPUnit\Framework\TestCase;

final class TranslationLoaderTest extends TestCase
{
    private function createLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'en',
            fuzzySearch: false,
        );
    }

    /**
     * @return iterable<string, array{string, array{string, string}}>
     */
    public static function parseKeyProvider(): iterable
    {
        yield 'empty string' => ['', ['*', '']];
        yield 'no dots' => ['foo', ['*', 'foo']];
        yield 'single dot' => ['foo.bar', ['*', 'foo.bar']];
        yield 'multiple dots' => ['foo.bar.baz', ['*', 'foo.bar.baz']];
        yield 'leading dot, single' => ['.foo', ['*', '.foo']];
        yield 'leading dot, multiple dots (regression)' => ['.foo.bar', ['*', '.foo.bar']];
        yield 'two leading dots' => ['..foo', ['*', '..foo']];
        yield 'trailing dot, single' => ['foo.', ['*', 'foo.']];
        yield 'trailing dot, multiple dots' => ['foo.bar.', ['*', 'foo.bar.']];
        yield 'two trailing dots' => ['foo..', ['*', 'foo..']];
        yield 'consecutive interior dots' => ['foo..bar', ['*', 'foo..bar']];
        yield 'namespaced single dot' => ['ns::foo.bar', ['ns', 'foo.bar']];
        yield 'namespaced multiple dots' => ['ns::foo.bar.baz', ['ns', 'foo.bar.baz']];
        yield 'namespaced no dot' => ['ns::foo', ['ns', 'foo']];
        yield 'empty namespace' => ['::foo', ['*', '::foo']];
        yield 'empty item' => ['ns::', ['*', 'ns::']];
    }

    /**
     * @dataProvider parseKeyProvider
     * @param array{string, string} $expected
     */
    public function testParseKey(string $input, array $expected): void
    {
        $loader = $this->createLoader();

        $this->assertSame($expected, $loader->parseKey($input));
    }

    /**
     * Regression: keys starting with `.` and containing two or more dots
     * previously triggered an `AssertionError` in `parseBasicSegments` due to
     * an operator-precedence bug (`$dotCount === 1 && ... || ...`).
     */
    public function testAddAcceptsKeyWithLeadingDotAndMultipleDots(): void
    {
        $loader = $this->createLoader();

        // Should not throw / assert.
        $loader->add('en', '.foo.bar', 'value');
        $loader->add('en', '..foo', 'value');
        $loader->add('en', 'foo.bar', 'value');

        $this->assertSame(['*', '.foo.bar'], $loader->parseKey('.foo.bar'));
        $this->assertSame(['*', '..foo'], $loader->parseKey('..foo'));
    }
}
