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

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\LostInTranslationCollector;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase as BaseRuleTestCase;

/**
 * @template T of Rule
 * @extends BaseRuleTestCase<T>
 */
abstract class RuleTestCase extends BaseRuleTestCase
{
    protected ?LostInTranslationHelper $lostInTranslationHelper = null;

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            new TranslationLoader(
                langPath: __DIR__ . '/lang',
                baseLocale: 'en',
            ),
        );
    }

    public function getLostInTranslationHelper(): LostInTranslationHelper
    {
        if (null === $this->lostInTranslationHelper) {
            $this->lostInTranslationHelper = $this->createLostInTranslationHelper();
        }

        return $this->lostInTranslationHelper;
    }

    public function getCollectors(): array
    {
        return [
            new LostInTranslationCollector($this->getLostInTranslationHelper()),
        ];
    }
}
