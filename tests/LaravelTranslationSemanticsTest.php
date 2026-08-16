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

        $phpCollisionPath = __DIR__ . '/lang-laravel-php-collision';

        yield 'PHP group item overrides dotted PHP group collision' => [$phpCollisionPath, [], 'en', 'a.b'];
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
