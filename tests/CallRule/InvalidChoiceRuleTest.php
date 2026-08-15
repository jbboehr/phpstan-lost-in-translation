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

use Illuminate\Translation\MessageSelector;
use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidChoiceRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\MetadataRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class InvalidChoiceRuleTest extends RuleTestCase
{
    private bool $requireCompleteChoiceCoverage = true;

    private bool $requireCompletePluralForms = false;

    /** @var array<string, string> */
    private array $localeAliases = [];

    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new InvalidChoiceRule(
                    requireCompleteChoiceCoverage: $this->requireCompleteChoiceCoverage,
                    requireCompletePluralForms: $this->requireCompletePluralForms,
                    translationLoader: $this->getTranslationLoader(),
                ),
            ]),
        );
    }

    public function testInvalidChoices(): void
    {
        $this->analyse([
            __DIR__ . '/../data/invalid-choice.php',
        ], [
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 3',
                7,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 2',
                10,
                Utils::formatTipForKeyValue('en', '{4,*} There are many|{3} There are three'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: int',
                15,
                Utils::formatTipForKeyValue('en', '{4,*} There are many|{3} There are three'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: int<2, 4>',
                29,
                Utils::formatTipForKeyValue('en', '{2} There are two|{3} There are three'),
            ],
            [
                'Translation choice has non-numeric value: "a"',
                32,
                Utils::formatTipForKeyValue('en', '{2} two|{a} three'),
            ],
            [
                'Translation choice has non-numeric value: "a"',
                33,
                Utils::formatTipForKeyValue('en', '{2} two|{3,a} three'),
            ],
            [
                'Failed to parse translation choice: "{3 three"',
                36,
                Utils::formatTipForKeyValue('en', '{2} two|{3 three'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 2',
                39,
                Utils::formatTipForKeyValue('en', '{*}all the things'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 4',
                42,
                Utils::formatTipForKeyValue('en', '{1,3} two'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 3',
                46,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 3',
                51,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 3',
                52,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 3',
                53,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Translation choice range must contain exactly two bounds; use "[2,4]" instead of "[2,3,4]" for contiguous values',
                56,
                Utils::formatTipForKeyValue('sk', '[2,3,4] There are :count books'),
            ],
            [
                'Translation choice has non-numeric value: "a"',
                59,
                Utils::formatTipForKeyValue('en', '{a} one|{b} two'),
            ],
            [
                'Translation choice has non-numeric value: "b"',
                59,
                Utils::formatTipForKeyValue('en', '{a} one|{b} two'),
            ],
            [
                'Failed to parse translation choice: "  {3 three"',
                62,
                Utils::formatTipForKeyValue('en', '  {3 three'),
            ],
        ]);
    }

    public function testCompleteChoiceCoverageUsesTheInferredNumberDomain(): void
    {
        $this->analyse([
            __DIR__ . '/../data/choice-coverage.php',
        ], [
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: int<0, max>',
                7,
                Utils::formatTipForKeyValue('en', '{1} There is one|[2,*] There are :count'),
            ],
            [
                'Translation choice range must contain exactly two bounds; use "[2,4]" instead of "[2,3,4]" for contiguous values',
                10,
                Utils::formatTipForKeyValue(
                    'sk',
                    '{0} Nie sú žiadne|{1} Je jedna|[2,3,4] Sú :count|[5,*] Je ich :count',
                ),
            ],
            [
                'Translation choice range must contain exactly two bounds; use "{2,4}" instead of "{2,3,4}" for contiguous values',
                13,
                Utils::formatTipForKeyValue('en', '{2,3,4} There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: int<0, max>',
                33,
                Utils::formatTipForKeyValue('en', '{1} There is one|[2,*] There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: 2',
                42,
                Utils::formatTipForKeyValue('en', '{1} There is one'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: int<0, max>',
                46,
                Utils::formatTipForKeyValue('en', '[1,*] There are :count'),
            ],
            [
                'Explicit translation choice conditions do not cover all possible cases for number of type: int',
                50,
                Utils::formatTipForKeyValue('en', '{1} There is one'),
            ],
        ]);
    }

    public function testCompleteChoiceCoverageCanBeDisabledIndependently(): void
    {
        $this->requireCompleteChoiceCoverage = false;

        $this->analyse([
            __DIR__ . '/../data/choice-coverage.php',
        ], [
            [
                'Translation choice range must contain exactly two bounds; use "[2,4]" instead of "[2,3,4]" for contiguous values',
                10,
                Utils::formatTipForKeyValue(
                    'sk',
                    '{0} Nie sú žiadne|{1} Je jedna|[2,3,4] Sú :count|[5,*] Je ich :count',
                ),
            ],
            [
                'Translation choice range must contain exactly two bounds; use "{2,4}" instead of "{2,3,4}" for contiguous values',
                13,
                Utils::formatTipForKeyValue('en', '{2,3,4} There are :count'),
            ],
        ]);
    }

    public function testCountableNormalizationPreservesOtherUnionMembers(): void
    {
        $value = '{0} There are none|[1,*] There are :count';

        foreach (
            [
                new ArrayType(new MixedType(), new MixedType()),
                new ObjectType(\Countable::class),
            ] as $countedType
        ) {
            $errors = (new InvalidChoiceRule())->processCall(new TranslationCall(
                className: null,
                functionName: 'trans_choice',
                file: __FILE__,
                line: 123,
                possibleTranslations: [
                    $value => [['en', null]],
                ],
                keyType: new ConstantStringType($value),
                numberType: new UnionType([
                    $countedType,
                    new ConstantIntegerType(-1),
                ]),
                isChoice: true,
            ));

            $this->assertCount(1, $errors);
            $this->assertSame(InvalidChoiceRule::IDENTIFIER_MISSING_CASE, $errors[0]->getIdentifier());
        }
    }

    public function testUnsupportedNumberTypesDoNotProduceCoverageNoise(): void
    {
        foreach (
            [
                ['{1} selected', new StringType(), []],
                [
                    '{1} selected',
                    new UnionType([new StringType(), new ConstantIntegerType(1)]),
                    [],
                ],
                [
                    '{1.5} selected',
                    new UnionType([new StringType(), new ConstantFloatType(1.5)]),
                    [],
                ],
                [
                    '{1} selected',
                    new UnionType([new StringType(), new IntegerType()]),
                    [[
                        InvalidChoiceRule::IDENTIFIER_MISSING_CASE,
                        'Explicit translation choice conditions do not cover all possible cases for number of type: int',
                    ]],
                ],
                [
                    '{1 selected',
                    new StringType(),
                    [[
                        InvalidChoiceRule::IDENTIFIER_MALFORMED,
                        'Failed to parse translation choice: "{1 selected"',
                    ]],
                ],
                ['{1} selected', new MixedType(), []],
                [
                    '{1} selected',
                    new UnionType([
                        new ArrayType(new MixedType(), new MixedType()),
                        new StringType(),
                    ]),
                    [[
                        InvalidChoiceRule::IDENTIFIER_MISSING_CASE,
                        'Explicit translation choice conditions do not cover all possible cases for number of type: int<0, max>',
                    ]],
                ],
                [
                    '{1} selected',
                    new UnionType([new ObjectType(\Countable::class), new StringType()]),
                    [[
                        InvalidChoiceRule::IDENTIFIER_MISSING_CASE,
                        'Explicit translation choice conditions do not cover all possible cases for number of type: int<0, max>',
                    ]],
                ],
            ] as [$value, $numberType, $expectedErrors]
        ) {
            $errors = (new InvalidChoiceRule())->processCall(new TranslationCall(
                className: null,
                functionName: 'trans_choice',
                file: __FILE__,
                line: 123,
                possibleTranslations: [
                    $value => [['en', null]],
                ],
                keyType: new ConstantStringType($value),
                numberType: $numberType,
                isChoice: true,
            ));

            $this->assertSame(
                $expectedErrors,
                array_map(
                    static fn(IdentifierRuleError $error): array => [$error->getIdentifier(), $error->getMessage()],
                    $errors,
                ),
                $value . ' with ' . $numberType->describe(\PHPStan\Type\VerbosityLevel::precise()),
            );
        }
    }

    public function testFractionalConditionsMatchLaravelMessageSelector(): void
    {
        $selector = new MessageSelector();

        foreach (
            [
                ['{1.5} selected', 1.5, true],
                ['[1.25,1.75] selected', 1.5, true],
                ['[-2.5,-1.5] selected', -2.0, true],
                ['[*,1.75] selected', 1.5, true],
                ['[1.25,*] selected', 1.5, true],
                ['{1} selected', 1.0, true],
                ['[*,*] selected', 1.5, true],
                ['{*} selected', 1.5, false],
                ['{1.5} selected', 1.0, false],
                ['[1.25,1.75] selected', 1, false],
                ['[-2.5,-1.5] selected', -1, false],
                ['{9007199254740993} selected', 9007199254740993, true],
                ['{9007199254740993} selected', 9007199254740992, false],
                ['{09007199254740993} selected', 9007199254740993, true],
                ['{-9007199254740993} selected', -9007199254740993, true],
                ['{-9007199254740993} selected', -9007199254740992, false],
                [sprintf('{%d} selected', PHP_INT_MAX), PHP_INT_MAX, true],
                [sprintf('{%d} selected', PHP_INT_MAX), 0, false],
                [sprintf('{%d} selected', PHP_INT_MIN), PHP_INT_MIN, true],
            ] as [$value, $number, $shouldSelect]
        ) {
            /** @phpstan-ignore-next-line argument.type Laravel supports floats despite the stale method PHPDoc. */
            $selected = $selector->choose('fallback zero|fallback one|' . $value, $number, 'en');
            $selectorDidSelect = 'selected' === $selected;

            $this->assertSame($shouldSelect, $selectorDidSelect, sprintf('%s with %s', $value, $number));

            $numberType = is_int($number)
                ? new ConstantIntegerType($number)
                : new ConstantFloatType($number);
            $errors = (new InvalidChoiceRule())->processCall(new TranslationCall(
                className: null,
                functionName: 'trans_choice',
                file: __FILE__,
                line: 123,
                possibleTranslations: [
                    $value => [['en', null]],
                ],
                keyType: new ConstantStringType($value),
                numberType: $numberType,
                isChoice: true,
            ));

            $this->assertSame(
                $shouldSelect ? [] : [InvalidChoiceRule::IDENTIFIER_MISSING_CASE],
                array_map(static fn(IdentifierRuleError $error): string => $error->getIdentifier(), $errors),
                sprintf('%s with %s', $value, $number),
            );
        }
    }

    public function testIntegerProjectionPreservesNumericBoundaries(): void
    {
        foreach (
            [
                ['[*,*] selected', true],
                ['{*} selected', false],
                ['{1.5} selected', false],
                [sprintf('[%d,*] selected', PHP_INT_MAX), false],
                [sprintf('[%d,*] selected', PHP_INT_MIN), true],
                [sprintf('[*,%d] selected', PHP_INT_MAX), true],
                [sprintf('[*,%d] selected', PHP_INT_MIN), false],
            ] as [$value, $coversAllIntegers]
        ) {
            $errors = (new InvalidChoiceRule())->processCall(new TranslationCall(
                className: null,
                functionName: 'trans_choice',
                file: __FILE__,
                line: 123,
                possibleTranslations: [
                    $value => [['en', null]],
                ],
                keyType: new ConstantStringType($value),
                numberType: new IntegerType(),
                isChoice: true,
            ));

            $this->assertSame(
                $coversAllIntegers ? [] : [InvalidChoiceRule::IDENTIFIER_MISSING_CASE],
                array_map(static fn(IdentifierRuleError $error): string => $error->getIdentifier(), $errors),
                $value,
            );
        }
    }

    public function testLaravelCompatibleUnconditionedChoices(): void
    {
        $this->analyse([
            __DIR__ . '/../data/valid-unconditioned-choice.php',
        ], []);
    }

    public function testLocaleAwarePluralCompletenessIsDisabledByDefault(): void
    {
        $value = 'Only form';
        $errors = (new InvalidChoiceRule())->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $value => [['en', null]],
            ],
            keyType: new ConstantStringType($value),
            numberType: new ConstantIntegerType(2),
            isChoice: true,
        ));

        $this->assertSame([], $errors);

        $this->analyse([
            __DIR__ . '/../data/plural-form-coverage.php',
        ], []);
    }

    public function testLocaleAwarePluralCompleteness(): void
    {
        $this->requireCompletePluralForms = true;
        $this->localeAliases = [
            'application_plural' => 'ar_SA',
        ];
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'en',
            localeAliases: $this->localeAliases,
        );

        $this->analyse([
            __DIR__ . '/../data/plural-form-coverage.php',
        ], [
            [
                'Translation choice provides 1 plural form, but locale "en" can select 2 forms',
                7,
                Utils::formatTipForKeyValue('en', 'Only form'),
            ],
            [
                'Translation choice provides 2 plural forms, but locale "ru" can select 3 forms',
                13,
                Utils::formatTipForKeyValue('ru', 'One|Many'),
            ],
            [
                'Translation choice provides 4 plural forms, but locale "ar" can select 6 forms',
                19,
                Utils::formatTipForKeyValue('ar', 'Zero|One|Two|Other'),
            ],
            [
                'Translation choice provides 1 plural form, but locale "is" can select 2 forms',
                25,
                Utils::formatTipForKeyValue('is', 'A revision'),
            ],
            [
                'Translation choice provides 1 plural form, but locale "APPLICATION-PLURAL" can select 6 forms',
                28,
                Utils::formatTipForKeyValue('APPLICATION-PLURAL', 'Application form'),
            ],
            [
                'Translation choice provides 3 plural forms, but locale "sl" can select 4 forms',
                31,
                Utils::formatTipForKeyValue('sl', 'One|Two|Other'),
            ],
            [
                'Translation choice provides 1 plural form, but locale "en_US" can select 2 forms',
                42,
                Utils::formatTipForKeyValue('en_US', 'Regional form'),
            ],
        ]);
    }

    public function testPluralCompletenessChecksAFullSentenceKeyAsTheSourceValue(): void
    {
        $key = 'A real full sentence with real shit in it.';
        $errors = (new InvalidChoiceRule(requireCompletePluralForms: true))->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $key => [['en', null]],
            ],
            keyType: new ConstantStringType($key),
            numberType: new ConstantIntegerType(2),
            isChoice: true,
        ));

        $this->assertCount(1, $errors);
        $this->assertSame(InvalidChoiceRule::IDENTIFIER_MISSING_PLURAL_FORM, $errors[0]->getIdentifier());
        $this->assertInstanceOf(MetadataRuleError::class, $errors[0]);
        $this->assertSame(
            Utils::metadata(key: $key, locale: 'en', value: $key),
            $errors[0]->getMetadata(),
        );
    }

    public function testPluralCompletenessKeepsTheKeyAndTranslatedValueSeparate(): void
    {
        $key = 'messages.revisions';
        $value = 'A revision';
        $errors = (new InvalidChoiceRule(requireCompletePluralForms: true))->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $key => [['is', $value]],
            ],
            keyType: new ConstantStringType($key),
            numberType: new ConstantIntegerType(2),
            isChoice: true,
        ));

        $this->assertCount(1, $errors);
        $this->assertSame(InvalidChoiceRule::IDENTIFIER_MISSING_PLURAL_FORM, $errors[0]->getIdentifier());
        $this->assertInstanceOf(MetadataRuleError::class, $errors[0]);
        $this->assertSame(
            Utils::metadata(key: $key, locale: 'is', value: $value),
            $errors[0]->getMetadata(),
        );
    }

    public function testPluralCompletenessSkipsMissingGroupedKeys(): void
    {
        foreach (
            [
                'messages.revisions',
                'messages.nested.revisions',
                'acme::messages.nested.revisions',
            ] as $key
        ) {
            $errors = (new InvalidChoiceRule(requireCompletePluralForms: true))->processCall(new TranslationCall(
                className: null,
                functionName: 'trans_choice',
                file: __FILE__,
                line: 123,
                possibleTranslations: [
                    $key => [['en', null]],
                ],
                keyType: new ConstantStringType($key),
                numberType: new ConstantIntegerType(2),
                isChoice: true,
            ));

            $this->assertSame([], $errors, $key);
        }
    }

    public function testPluralCompletenessDoesNotCascadeFromAMalformedMixedChoice(): void
    {
        $value = '{0 None|Other';
        $errors = (new InvalidChoiceRule(requireCompletePluralForms: true))->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $value => [['en', null]],
            ],
            keyType: new ConstantStringType($value),
            numberType: new ConstantIntegerType(2),
            isChoice: true,
        ));

        $this->assertCount(1, $errors);
        $this->assertSame(InvalidChoiceRule::IDENTIFIER_MALFORMED, $errors[0]->getIdentifier());
    }

    public function testExplicitOnlyChoicesRemainUnderExplicitCoverage(): void
    {
        $value = '{0} None';
        $errors = (new InvalidChoiceRule(requireCompletePluralForms: true))->processCall(new TranslationCall(
            className: null,
            functionName: 'trans_choice',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $value => [['ar', null]],
            ],
            keyType: new ConstantStringType($value),
            numberType: new ConstantIntegerType(1),
            isChoice: true,
        ));

        $this->assertCount(1, $errors);
        $this->assertSame(InvalidChoiceRule::IDENTIFIER_MISSING_CASE, $errors[0]->getIdentifier());
    }

    public function testSourceStringFallbackWithFilelessBaseLocale(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'fr',
        );

        $this->analyse([
            __DIR__ . '/../data/fileless-base-fallback.php',
        ], []);
    }
}
