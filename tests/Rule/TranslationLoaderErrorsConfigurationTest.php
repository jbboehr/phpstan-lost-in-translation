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

namespace jbboehr\PHPStanLostInTranslation\Tests\Rule;

use jbboehr\PHPStanLostInTranslation\CollectedDataNodeTriggerCollector;
use jbboehr\PHPStanLostInTranslation\Rule\TranslationLoaderErrorRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<TranslationLoaderErrorRule>
 */
final class TranslationLoaderErrorsConfigurationTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(TranslationLoaderErrorRule::class);
    }

    public function testLoaderErrorsRunWithoutUnrelatedCollectedData(): void
    {
        $errors = $this->gatherAnalyserErrors([
            __DIR__ . '/../data/configuration-independent-checks.php',
        ]);

        self::assertCount(7, $errors);

        foreach ($errors as $error) {
            self::assertSame('lostInTranslation.translationLoaderError', $error->getIdentifier());
        }
    }

    public function getCollectors(): array
    {
        return [self::getContainer()->getByType(CollectedDataNodeTriggerCollector::class)];
    }

    public static function getAdditionalConfigFiles(): array
    {
        return array_merge(parent::getAdditionalConfigFiles(), [
            __DIR__ . '/../configuration-loader-errors-only.neon',
        ]);
    }
}
