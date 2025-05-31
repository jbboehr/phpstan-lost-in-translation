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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rule\TranslationLoaderWarningRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\JsonLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<TranslationLoaderWarningRule>
 */
class TranslationLoaderWarningRuleTest extends RuleTestCase
{
    private TranslationLoader $translationLoader;

    public function setUp(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/lang-warn',
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
            $this->translationLoader,
        );
    }

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper($this->translationLoader);
    }

    public function testWarnings(): void
    {
        $this->analyse([
            __DIR__ . '/data/translation-loader-warning.php',
        ], [
            // lang-warn/es.json
            [
                "Invalid key: 0",
                2,
            ],
            // lang-warn/ja.json
            [
                "Failed to parse JSON file: Syntax error",
                -1,
            ],
            // lang-warn/pt.json
            [
                "Invalid value: 1",
                2,
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
            }
        ]);
    }
}
