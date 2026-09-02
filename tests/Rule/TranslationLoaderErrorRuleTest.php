<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
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

namespace Rule;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\Rule\TranslationLoaderErrorRule;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<TranslationLoaderErrorRule>
 */
class TranslationLoaderErrorRuleTest extends RuleTestCase
{
    public function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/lang-warn',
            baseLocale: null,
        );
    }

    public function tearDown(): void
    {
        unset($this->translationLoader);

        parent::tearDown();

        if (class_exists(HandleExceptions::class, false) && method_exists(HandleExceptions::class, 'flushState')) {
            HandleExceptions::flushState();
        }
    }

    protected function getRule(): Rule
    {
        return new TranslationLoaderErrorRule(
            $this->getTranslationLoader(),
        );
    }

    public function testWarnings(): void
    {
        $this->analyse([
            __DIR__ . '/data/translation-loader-error.php',
        ], [
            // lang-warn/es.json
            [
                "Invalid key: 0",
                2,
            ],
            // lang-warn/ja.json
            [
                "Failed to parse JSON: Syntax error",
                -1,
            ],
            // lang-warn/pt.json
            [
                "Invalid value: 1",
                2,
            ],
            [
                'Invalid value: {"at least":"we should not throw"}',
                3,
            ],
            // lang/zh/even-more-messages.php
            [
                'Invalid data type "string"',
                -1,
            ],
            // lang-warn/zh/messages.php
            [
                "Failed to parse file with error: Syntax error, unexpected EOF, expecting ',' or ']' or ')' on line 3",
                3,
            ],
            // lang/zh/more-messages.php
            [
                "Invalid value: 1",
                2,
            ],
            // lang/invalid_locale.json
            [
                'Unknown locale: invalid_locale',
                -1,
            ],
        ]);
    }

    public function testConfiguredLocaleAliasSuppressesTheLoaderLocaleDiagnostic(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'de_informal' => 'de_DE',
            ],
        );

        $this->analyse([
            __DIR__ . '/data/translation-loader-error.php',
        ], []);
    }

    public function testLoaderErrorsCanBeEnabledWithoutLocaleValidation(): void
    {
        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $node = new CollectedDataNode([], false);
        $rule = new TranslationLoaderErrorRule(
            $this->createTranslationLoader(),
            invalidLocales: false,
            translationLoaderErrors: true,
        );

        $errors = $rule->processNode($node, $this->createStub(Scope::class));

        $this->assertCount(7, $errors);
        $this->assertNotContains(
            InvalidLocaleRule::IDENTIFIER_UNKNOWN_LOCALE,
            array_map(static fn ($error): string => $error->getIdentifier(), $errors),
        );
    }

    public function testDisablingBothLoaderChecksSuppressesParseValueAndConflictDiagnostics(): void
    {
        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $node = new CollectedDataNode([], false);

        foreach (
            [
                $this->createTranslationLoader(),
                new TranslationLoader(
                    langPath: __DIR__ . '/../lang-laravel-semantics',
                    baseLocale: 'en',
                    fuzzySearch: false,
                ),
            ] as $loader
        ) {
            $rule = new TranslationLoaderErrorRule(
                $loader,
                invalidLocales: false,
                translationLoaderErrors: false,
            );

            $this->assertSame([], $rule->processNode($node, $this->createStub(Scope::class)));
        }
    }

    public function getCollectors(): array
    {
        return array_merge(parent::getCollectors(), [
            new class implements \PHPStan\Collectors\Collector {
                public function getNodeType(): string
                {
                    return Node::class;
                }

                public function processNode(Node $node, Scope $scope): mixed
                {
                    return true;
                }
            },
        ]);
    }

    public function testExceptionConversion(): void
    {
        $ex = new \RuntimeException(self::class);
        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $node = new CollectedDataNode([], false);

        $loader = $this->createMock(TranslationLoader::class);
        $loader->method('getErrors')
            ->willThrowException($ex);

        $obj = new TranslationLoaderErrorRule($loader);

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('phpstan-lost-in-translation');

        $obj->processNode(
            $node,
            $this->createStub(Scope::class),
        );
    }
}
