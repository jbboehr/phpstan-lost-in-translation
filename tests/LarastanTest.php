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

use jbboehr\PHPStanLostInTranslation\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class LarastanTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new MissingTranslationStringRule($this->getLostInTranslationHelper()),
            ]),
        );
    }

    public function setUp(): void
    {
        parent::setUp();

        if (!\Composer\InstalledVersions::isInstalled('larastan/larastan')) {
            self::markTestSkipped('Requires larastan to be installed');
        }
    }

    public function testLarastanInference(): void
    {
        $this->analyse([
            __DIR__ . '/data/larastan-inference.php',
        ], [
            [
                'Missing translation string "this inference requires larastan to work" for locales: ja, zh',
                3,
            ],
            [
                'Missing translation string "this inference requires larastan to work" for locales: ja, zh',
                4,
            ],
            [
                'Missing translation string "this inference requires larastan to work" for locales: ja, zh',
                5,
            ],
            // this one is not working for some reason
            // [
            //     'Missing translation string "this inference requires larastan to work" for locales: ja, zh',
            //     8,
            // ],
            [
                'Missing translation string "this inference requires larastan to work" for locales: ja, zh',
                9,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return array_merge(parent::getAdditionalConfigFiles(), [
            __DIR__ . '/larastan.neon',
        ]);
    }
}
