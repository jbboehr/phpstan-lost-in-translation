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

namespace jbboehr\PHPStanLostInTranslation\Tests\CallRule;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\DynamicTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class DynamicTranslationStringRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new DynamicTranslationStringRule(),
            ]),
        );
    }

    public function testDynamicTranslationString(): void
    {
        $this->analyse([
            __DIR__ . '/../data/dynamic-translation-string.php',
        ], [
            [
                'Disallowed dynamic translation string of type: string',
                5,
            ],
            [
                "Disallowed dynamic translation string of type: 'bar'|'foo'|Exception",
                8,
            ],
        ]);
    }
}
