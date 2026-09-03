<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
 *
 * SPDX-License-Identifier: AGPL-3.0-only WITH romic-exception
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License version 3,
 * as published by the Free Software Foundation, together with the Romic
 * Exception (an additional permission under section 7 of that license).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * and the Romic Exception along with this program.  If not, see
 * <http://www.gnu.org/licenses/> and docs/LICENSE_EXCEPTION.md.
 */
declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\PropertyTests;

use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use jbboehr\PHPStanLostInTranslation\Fuzzy\MemoizingFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\Fuzzy\NaiveFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\JsonLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\LineRuleError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

final class TranslationPropertiesTest extends TestCase
{
    use TestTrait;

    public function testFlexibleLocaleCanonicalizationIsIdempotent(): void
    {
        $this
            ->forAll(self::localeSpellingGenerator())
            ->then(static function (array $spelling): void {
                $canonical = Utils::canonicalizeLocale($spelling['variant']);

                self::assertSame($canonical, Utils::canonicalizeLocale($canonical));
            });
    }

    public function testFlexibleLocaleCanonicalizationIgnoresCaseAndSeparator(): void
    {
        $this
            ->forAll(self::localeSpellingGenerator())
            ->then(static function (array $spelling): void {
                self::assertSame($spelling['canonical'], Utils::canonicalizeLocale($spelling['variant']));
            });
    }

    public function testStrictLocaleCanonicalizationPreservesExactSpelling(): void
    {
        $this
            ->forAll(self::localeSpellingGenerator())
            ->then(static function (array $spelling): void {
                self::assertSame($spelling['variant'], Utils::canonicalizeLocale($spelling['variant'], true));
            });
    }

    public function testNamespacedAndPlainTranslationLookupIsIndependentOfInsertionOrder(): void
    {
        $plainFirst = self::createTranslationLoader();
        $namespacedFirst = self::createTranslationLoader();

        $this
            ->forAll(
                Generators::elements(['vendor', 'package', 'acme', 'translations']),
                Generators::elements(['messages', 'validation', 'auth', 'sentences']),
                Generators::elements(['title', 'body', 'missing', 'full.sentence']),
            )
            ->then(static function (string $namespace, string $group, string $item) use (
                $plainFirst,
                $namespacedFirst,
            ): void {
                $plainKey = $group . '.' . $item;
                $namespacedKey = $namespace . '::' . $plainKey;

                $plainFirst->add('en', $plainKey, 'Plain translation');
                $plainFirst->add('en', $namespacedKey, 'Namespaced translation');

                $namespacedFirst->add('en', $namespacedKey, 'Namespaced translation');
                $namespacedFirst->add('en', $plainKey, 'Plain translation');

                self::assertSame('Plain translation', $plainFirst->get('en', $plainKey));
                self::assertSame('Namespaced translation', $plainFirst->get('en', $namespacedKey));
                self::assertSame('Plain translation', $namespacedFirst->get('en', $plainKey));
                self::assertSame('Namespaced translation', $namespacedFirst->get('en', $namespacedKey));
            });
    }

    public function testMemoizingFuzzySearchMatchesUncachedReference(): void
    {
        $this
            ->forAll(self::fuzzyOperationSequenceGenerator())
            ->then(static function (array $operations): void {
                $reference = new NaiveFuzzyStringSet();
                $memoized = new MemoizingFuzzyStringSet(new NaiveFuzzyStringSet());

                foreach ($operations as [$operation, $first, $second]) {
                    if ('add' === $operation) {
                        $reference->add($first);
                        $memoized->add($first);
                    } elseif ('addMany' === $operation) {
                        $reference->addMany([$first, $second]);
                        $memoized->addMany([$first, $second]);
                    } else {
                        self::assertSame($reference->search($first), $memoized->search($first));
                    }
                }
            });
    }

    public function testJsonLoaderMatchesReferenceForGeneratedCatalogues(): void
    {
        $this
            ->forAll(self::jsonEntryListGenerator())
            ->then(static function (array $entries): void {
                $members = [];
                $expectedLocations = [];

                foreach ($entries as $index => [$key, $value]) {
                    $members[] = sprintf(
                        '  %s: %s',
                        json_encode($key, JSON_THROW_ON_ERROR),
                        json_encode($value, JSON_THROW_ON_ERROR),
                    );

                    if ('' !== $key) {
                        $expectedLocations[$key] = $index + 2;
                    }
                }

                $json = sprintf("{\n%s\n}\n", implode(",\n", $members));
                $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                $expectedTranslations = [];
                $expectedErrors = [];

                foreach ($decoded as $key => $value) {
                    $line = $expectedLocations[$key] ?? $expectedLocations["int\0" . $key] ?? -1;

                    if (!is_string($key)) {
                        $expectedErrors[] = [
                            'message' => sprintf('Invalid key: %d', $key),
                            'line' => $line,
                        ];
                    } elseif (!is_string($value)) {
                        $expectedErrors[] = [
                            'message' => sprintf('Invalid value: %s', json_encode($value, JSON_THROW_ON_ERROR)),
                            'line' => $line,
                        ];
                    } elseif ('' !== $key && '' !== $value) {
                        $expectedTranslations[$key] = $value;
                    }
                }

                $path = tempnam(sys_get_temp_dir(), 'phpstan-lost-in-translation-property-');
                self::assertIsString($path);

                try {
                    self::assertNotFalse(file_put_contents($path, $json));

                    $result = (new JsonLoader())->load(new SplFileInfo($path, '', basename($path)));
                    $actualErrors = array_map(
                        static fn($error): array => [
                            'message' => $error->getMessage(),
                            'line' => $error instanceof LineRuleError ? $error->getLine() : null,
                        ],
                        $result->errors,
                    );

                    self::assertSame($expectedTranslations, $result->translations);
                    self::assertSame($expectedLocations, $result->locations);
                    self::assertSame($expectedErrors, $actualErrors);
                } finally {
                    unlink($path);
                }
            });
    }

    public function testDiagnosticEscapingHandlesArbitraryBytes(): void
    {
        $this
            ->forAll(self::binaryStringGenerator())
            ->then(static function (string $value): void {
                try {
                    $expected = json_encode($value, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    self::assertStringContainsString('Malformed UTF-8 characters', $exception->getMessage());
                    $escaped = preg_replace_callback(
                        '/["\x00-\x1f\x7f-\xff]/',
                        static function (array $matches): string {
                            if ('"' === $matches[0]) {
                                return '\\"';
                            }

                            return sprintf('\\x%02x', ord($matches[0]));
                        },
                        $value,
                    );
                    self::assertIsString($escaped);
                    $expected = '"' . $escaped . '"';
                }

                self::assertSame($expected, Utils::e($value));
            });
    }

    private static function localeSpellingGenerator(): Generator
    {
        return Generators::map(
            static function (array $parts): array {
                [$canonical, $separator, $case] = $parts;
                $variant = str_replace('_', $separator, $canonical);

                if ('lower' === $case) {
                    $variant = strtolower($variant);
                } elseif ('upper' === $case) {
                    $variant = strtoupper($variant);
                }

                return [
                    'canonical' => $canonical,
                    'variant' => $variant,
                ];
            },
            Generators::tuple(
                Generators::elements(['en', 'pt_BR', 'zh_Hans', 'sr_Latn_RS', 'es_419']),
                Generators::elements(['_', '-']),
                Generators::elements(['canonical', 'lower', 'upper']),
            ),
        );
    }

    private static function nonEmptyAsciiStringGenerator(): Generator
    {
        $characters = str_split('abcdefghijklmnopqrstuvwxyz0123456789._:-/ ');

        return Generators::bind(
            Generators::choose(1, 16),
            static fn(int $length): Generator => Generators::map(
                static fn(array $value): string => implode('', $value),
                Generators::vector($length, Generators::elements($characters)),
            ),
        );
    }

    private static function fuzzyOperationSequenceGenerator(): Generator
    {
        return Generators::vector(
            20,
            Generators::tuple(
                Generators::elements(['add', 'addMany', 'search']),
                self::nonEmptyAsciiStringGenerator(),
                self::nonEmptyAsciiStringGenerator(),
            ),
        );
    }

    private static function jsonEntryListGenerator(): Generator
    {
        $keyGenerator = Generators::oneOf(
            self::nonEmptyAsciiStringGenerator(),
            Generators::elements(['', '0', '01', '-1', 'quoted"key', 'backslash\\key']),
        );
        $valueGenerator = Generators::oneOf(
            self::nonEmptyAsciiStringGenerator(),
            Generators::constant(''),
            Generators::choose(-50, 50),
            Generators::bool(),
            Generators::constant(null),
            Generators::vector(2, Generators::choose(-5, 5)),
        );

        return Generators::bind(
            Generators::choose(0, 12),
            static fn(int $length): Generator => Generators::vector(
                $length,
                Generators::tuple($keyGenerator, $valueGenerator),
            ),
        );
    }

    private static function binaryStringGenerator(): Generator
    {
        return Generators::map(
            static fn(array $bytes): string => implode('', array_map('chr', $bytes)),
            Generators::seq(Generators::byte()),
        );
    }

    private static function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/../../../tests/lang',
            baseLocale: 'en',
            fuzzySearch: false,
        );
    }
}
