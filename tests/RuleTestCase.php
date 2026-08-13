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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringCollector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase as BaseRuleTestCase;

/**
 * @template T of Rule
 * @extends BaseRuleTestCase<T>
 */
abstract class RuleTestCase extends BaseRuleTestCase
{
    protected ?LostInTranslationHelper $lostInTranslationHelper = null;

    protected ?TranslationLoader $translationLoader = null;

    protected bool $fuzzySearch = true;

    public function tearDown(): void
    {
        $this->lostInTranslationHelper = null;
        $this->translationLoader = null;

        parent::tearDown();
    }

    public function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'en',
            fuzzySearch: $this->fuzzySearch,
        );
    }

    public function getTranslationLoader(): TranslationLoader
    {
        if (null === $this->translationLoader) {
            $this->translationLoader = $this->createTranslationLoader();
        }

        return $this->translationLoader;
    }

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            $this->getTranslationLoader(),
            self::getContainer()->getByType(ReflectionProvider::class),
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
            new UnusedTranslationStringCollector($this->getLostInTranslationHelper()),
        ];
    }
}
