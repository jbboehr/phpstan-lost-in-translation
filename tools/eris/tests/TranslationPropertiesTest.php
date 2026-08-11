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
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPUnit\Framework\TestCase;

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

    private static function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/../../../tests/lang',
            baseLocale: 'en',
            fuzzySearch: false,
        );
    }
}
