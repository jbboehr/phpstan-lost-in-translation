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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;

final class LaravelTranslationSemanticsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideLookupCases
     * @param list<non-empty-string> $namespaces
     */
    public function testLookupMatchesLaravel(
        string $langPath,
        array $namespaces,
        string $locale,
        string $key,
    ): void {
        $extensionLoader = new TranslationLoader(
            langPath: $langPath,
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $laravelLoader = new FileLoader(new Filesystem(), $langPath);

        foreach ($namespaces as $namespace) {
            $laravelLoader->addNamespace($namespace, $langPath . '/package-' . $namespace);
        }

        $translator = new Translator($laravelLoader, 'en');
        // Missing-locale coverage is analyzed separately, so compare one exact locale without Laravel fallback.
        $runtimeValue = $translator->get($key, [], $locale, false);

        $this->assertNotSame($key, $runtimeValue, 'The Laravel oracle fixture must define the lookup key');
        $this->assertSame(
            $this->normalizeRuntimeValue($runtimeValue),
            $extensionLoader->get($locale, $key),
        );
    }

    /**
     * @return iterable<string, array{non-empty-string, list<non-empty-string>, non-empty-string, non-empty-string}>
     */
    public static function provideLookupCases(): iterable
    {
        $scanningPath = __DIR__ . '/lang-scanning';

        yield 'root JSON string' => [$scanningPath, [], 'en', 'root'];
        yield 'grouped PHP string' => [$scanningPath, [], 'en', 'messages.grouped'];
        yield 'entire PHP group' => [$scanningPath, [], 'en', 'messages'];
        yield 'vendor override string' => [$scanningPath, ['acme'], 'en', 'acme::messages.shared'];
        yield 'other vendor override string' => [$scanningPath, ['other'], 'en', 'other::messages.shared'];
        yield 'vendor override array' => [$scanningPath, ['acme'], 'en', 'acme::messages.options'];
        yield 'entire vendor override group' => [$scanningPath, ['acme'], 'en', 'acme::messages'];
        yield 'vendor override in another locale' => [$scanningPath, ['acme'], 'ja', 'acme::messages.shared'];

        $arrayPath = __DIR__ . '/lang-array-values';

        yield 'literal dotted item' => [$arrayPath, [], 'en', 'messages.options.one'];
        yield 'literal dotted item before nested array' => [$arrayPath, [], 'en', 'messages.options.nested'];
        yield 'traversed nested item' => [$arrayPath, [], 'en', 'messages.options.two'];
        yield 'array-valued parent' => [$arrayPath, [], 'en', 'messages.options'];
        yield 'entire group with dotted collisions' => [$arrayPath, [], 'en', 'messages'];
        yield 'scalar where another locale has an array' => [$arrayPath, [], 'ja', 'messages.options'];
        yield 'array where another locale has a scalar' => [$arrayPath, [], 'zh', 'messages.options'];

        $precedencePath = __DIR__ . '/lang-laravel-semantics';

        yield 'JSON exact key precedes grouped item' => [$precedencePath, [], 'en', 'messages.collision'];
        yield 'ordinary grouped item beside JSON collision' => [$precedencePath, [], 'en', 'messages.grouped'];
        yield 'whole group retains JSON-shadowed PHP item' => [$precedencePath, [], 'en', 'messages'];
    }

    public function testJsonAndGroupedCollisionRetainsDiagnostic(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-laravel-semantics',
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $this->assertCount(1, $loader->getErrors());
        $this->assertSame(TranslationLoader::IDENTIFIER_CONFLICT, $loader->getErrors()[0]->getIdentifier());
    }

    /**
     * @dataProvider provideGeneratedPhpCatalogues
     * @param array<array-key, mixed> $catalogue
     * @param list<non-empty-string> $keys
     * @param list<non-empty-string> $expectedErrorIdentifiers
     */
    public function testGeneratedPhpCatalogueMatchesLaravel(
        array $catalogue,
        array $keys,
        array $expectedErrorIdentifiers,
    ): void {
        $langPath = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $localePath = $langPath . '/en';
        $translationFile = $localePath . '/messages.php';

        $this->assertTrue(mkdir($localePath, recursive: true));

        try {
            $this->assertNotFalse(file_put_contents(
                $translationFile,
                "<?php\n\nreturn " . var_export($catalogue, true) . ";\n",
            ));

            $extensionLoader = new TranslationLoader(
                langPath: $langPath,
                baseLocale: 'en',
                fuzzySearch: false,
            );
            $translator = new Translator(new FileLoader(new Filesystem(), $langPath), 'en');

            foreach ($keys as $key) {
                $runtimeValue = $translator->get($key, [], 'en', false);

                $this->assertNotSame($key, $runtimeValue, 'The generated Laravel fixture must define ' . $key);
                $this->assertSame(
                    $this->normalizeRuntimeValue($runtimeValue),
                    $extensionLoader->get('en', $key),
                    $key,
                );
            }

            $this->assertSame(
                $expectedErrorIdentifiers,
                array_map(static fn ($error): string => $error->getIdentifier(), $extensionLoader->getErrors()),
            );
        } finally {
            unlink($translationFile);
            rmdir($localePath);
            rmdir($langPath);
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, list<non-empty-string>, list<non-empty-string>}>
     */
    public static function provideGeneratedPhpCatalogues(): iterable
    {
        foreach (
            [
                'plain segments' => ['alpha', 'beta'],
                'punctuated segments' => ['snake_key', 'kebab-key'],
                'numeric segments' => [0, 1],
            ] as $name => [$parent, $leaf]
        ) {
            $parentKey = (string) $parent;
            $leafKey = (string) $leaf;
            $literalKey = $parentKey . '.' . $leafKey;
            $keys = ['messages', 'messages.' . $parentKey, 'messages.' . $literalKey];

            yield $name . ', literal scalar first' => [
                [
                    $literalKey => 'Literal dotted scalar',
                    $parent => [$leaf => 'Traversed scalar'],
                ],
                $keys,
                [],
            ];
            yield $name . ', traversed scalar first' => [
                [
                    $parent => [$leaf => 'Traversed scalar'],
                    $literalKey => 'Literal dotted scalar',
                ],
                $keys,
                [],
            ];
            yield $name . ', literal array first' => [
                [
                    $literalKey => ['literal' => 'Literal dotted array'],
                    $parent => [$leaf => ['traversed' => 'Traversed array']],
                ],
                [...$keys, 'messages.' . $literalKey . '.traversed'],
                [],
            ];
            yield $name . ', traversed array first' => [
                [
                    $parent => [$leaf => ['traversed' => 'Traversed array']],
                    $literalKey => ['literal' => 'Literal dotted array'],
                ],
                [...$keys, 'messages.' . $literalKey . '.traversed'],
                [],
            ];
            yield $name . ', nested dotted scalar is not exact' => [
                [
                    $parent => [
                        $leaf => ['tail' => 'Traversed nested scalar'],
                        $leafKey . '.tail' => 'Nested dotted scalar',
                    ],
                ],
                ['messages', 'messages.' . $parentKey, 'messages.' . $literalKey . '.tail'],
                [],
            ];
            yield $name . ', nested dotted array is not exact' => [
                [
                    $parent => [
                        $leafKey . '.tail' => ['literal' => 'Nested dotted array'],
                        $leaf => ['tail' => 'Traversed nested array'],
                    ],
                ],
                ['messages', 'messages.' . $parentKey, 'messages.' . $literalKey . '.tail'],
                [],
            ];
        }

        yield 'mixed values and invalid bytes' => [
            [
                'valid' => 'Valid scalar',
                'invalid' => 42,
                'parent' => [
                    'bad' => "\xff",
                    'good' => 'Valid nested scalar',
                    'empty' => [],
                ],
            ],
            ['messages', 'messages.valid', 'messages.parent', 'messages.parent.bad', 'messages.parent.good'],
            [PhpLoader::IDENTIFIER, InvalidCharacterEncodingRule::IDENTIFIER],
        ];
    }

    /**
     * @dataProvider provideGeneratedPhpGroupCollisions
     * @param array<array-key, array<array-key, mixed>> $groups
     * @param list<non-empty-string> $keys
     */
    public function testGeneratedPhpGroupCollisionMatchesLaravel(
        array $groups,
        array $keys,
        ?string $namespace,
    ): void {
        $langPath = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $localePath = null === $namespace
            ? $langPath . '/en'
            : $langPath . '/vendor/' . $namespace . '/en';

        $this->assertTrue(mkdir($localePath, recursive: true));

        try {
            foreach ($groups as $group => $catalogue) {
                $this->assertNotFalse(file_put_contents(
                    $localePath . '/' . $group . '.php',
                    "<?php\n\nreturn " . var_export($catalogue, true) . ";\n",
                ));
            }

            $extensionLoader = new TranslationLoader(
                langPath: $langPath,
                baseLocale: 'en',
                fuzzySearch: false,
            );
            $laravelLoader = new FileLoader(new Filesystem(), $langPath);

            if (null !== $namespace) {
                $laravelLoader->addNamespace($namespace, $langPath . '/package-' . $namespace);
            }

            $translator = new Translator($laravelLoader, 'en');

            foreach ($keys as $key) {
                $runtimeValue = $translator->get($key, [], 'en', false);

                $this->assertNotSame($key, $runtimeValue, 'The generated Laravel fixture must define ' . $key);
                $this->assertSame(
                    $this->normalizeRuntimeValue($runtimeValue),
                    $extensionLoader->get('en', $key),
                    $key,
                );
            }

            $this->assertSame(
                [TranslationLoader::IDENTIFIER_CONFLICT, TranslationLoader::IDENTIFIER_CONFLICT],
                array_map(static fn ($error): string => $error->getIdentifier(), $extensionLoader->getErrors()),
            );
        } finally {
            foreach (array_keys($groups) as $group) {
                unlink($localePath . '/' . $group . '.php');
            }

            rmdir($localePath);

            if (null !== $namespace) {
                rmdir(dirname($localePath));
                rmdir(dirname($localePath, 2));
            }

            rmdir($langPath);
        }
    }

    /**
     * @return iterable<string, array{
     *     array<array-key, array<array-key, mixed>>,
     *     list<non-empty-string>,
     *     ?non-empty-string
     * }>
     */
    public static function provideGeneratedPhpGroupCollisions(): iterable
    {
        foreach (
            [
                'plain segments' => ['alpha', 'beta'],
                'punctuated segments' => ['snake_key', 'kebab-key'],
                'numeric segments' => ['0', '1'],
            ] as $name => [$group, $item]
        ) {
            foreach (['plain' => null, 'namespaced' => 'acme'] as $layout => $namespace) {
                $externalPrefix = null === $namespace ? '' : $namespace . '::';
                $key = $externalPrefix . $group . '.' . $item;

                yield $layout . ', ' . $name => [
                    [
                        $group . '.' . $item => ['tail' => 'Dotted group value'],
                        $group => [$item => ['tail' => 'Laravel group value']],
                    ],
                    [$key, $key . '.tail'],
                    $namespace,
                ];
            }
        }
    }

    private function normalizeRuntimeValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $this->assertIsArray($value);
        $values = [];

        array_walk_recursive($value, static function (mixed $leaf) use (&$values): void {
            if (is_string($leaf) && '' !== $leaf) {
                $values[] = $leaf;
            }
        });

        $this->assertNotSame([], $values, 'Array-valued oracle fixtures must contain a non-empty string leaf');

        return implode("\n", $values);
    }
}
