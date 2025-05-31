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

namespace Rule;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use jbboehr\PHPStanLostInTranslation\Rule\TranslationLoaderWarningRule;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\JsonLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<TranslationLoaderWarningRule>
 */
class TranslationLoaderWarningRuleTest extends RuleTestCase
{
    public function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/../lang-warn',
            baseLocale: null,
            phpLoader: new PhpLoader(),
            jsonLoader: new JsonLoader(),
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
        return new TranslationLoaderWarningRule(
            $this->getTranslationLoader(),
        );
    }

    public function testWarnings(): void
    {
        $this->analyse([
            __DIR__ . '/../data/translation-loader-warning.php',
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
        if (!class_exists(FuncCall::class)) {
            $this->markTestIncomplete('This seems to fail when you filter, probably PHPStan autoload does not get initialized');
        }

        $ex = new \RuntimeException(self::class);
        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $node = new CollectedDataNode([], false);

        $loader = $this->createMock(TranslationLoader::class);
        $loader->method('getErrors')
            ->willThrowException($ex);

        $obj = new TranslationLoaderWarningRule($loader);

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('phpstan-lost-in-translation');

        $obj->processNode(
            $node,
            $this->createStub(Scope::class),
        );
    }
}
