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

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidChoiceRule;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Rule\TranslationLoaderErrorRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
final class ConfigurationIntegrationTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(LostInTranslationRule::class);
    }

    public function testConfiguredChoiceFlagsApplyDuringPHPStanAnalysis(): void
    {
        $errors = $this->gatherAnalyserErrors([
            __DIR__ . '/data/configuration-independent-checks.php',
        ]);

        self::assertCount(1, $errors);
        self::assertSame(InvalidChoiceRule::IDENTIFIER_MISSING_PLURAL_FORM, $errors[0]->getIdentifier());
        self::assertSame(7, $errors[0]->getLine());
    }

    public function testPluralCompletenessCanBeEnabledWithoutSyntaxValidation(): void
    {
        $choiceRules = array_values(array_filter(
            iterator_to_array(self::getContainer()->getByType(CallRuleCollection::class)),
            static fn ($rule): bool => $rule instanceof InvalidChoiceRule,
        ));

        self::assertCount(1, $choiceRules);
        $choiceRule = $choiceRules[0];

        $malformedValue = '{1 malformed|Other';
        $malformedErrors = $choiceRule->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $malformedValue => [['ru', null]],
            ],
            keyType: new ConstantStringType($malformedValue),
            numberType: new ConstantIntegerType(1),
            isChoice: true,
        ));

        self::assertSame([], $malformedErrors);

        $pluralValue = 'Only form';
        $pluralErrors = $choiceRule->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $pluralValue => [['en', null]],
            ],
            keyType: new ConstantStringType($pluralValue),
            numberType: new ConstantIntegerType(2),
            isChoice: true,
        ));

        self::assertCount(1, $pluralErrors);
        self::assertSame(InvalidChoiceRule::IDENTIFIER_MISSING_PLURAL_FORM, $pluralErrors[0]->getIdentifier());
    }

    public function testFileLocaleValidationCanBeEnabledWithoutLoaderErrors(): void
    {
        $container = self::getContainer();
        $rule = $container->getByType(TranslationLoaderErrorRule::class);

        self::assertTrue(in_array($rule, $container->getServicesByTag('phpstan.rules.rule'), true));

        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $node = new CollectedDataNode([], false);
        $errors = $rule->processNode($node, $this->createStub(Scope::class));

        self::assertCount(1, $errors);
        self::assertSame(InvalidLocaleRule::IDENTIFIER_UNKNOWN_LOCALE, $errors[0]->getIdentifier());
    }

    public static function getAdditionalConfigFiles(): array
    {
        return array_merge(parent::getAdditionalConfigFiles(), [
            __DIR__ . '/configuration-independent-checks.neon',
        ]);
    }
}
