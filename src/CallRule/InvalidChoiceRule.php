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

namespace jbboehr\PHPStanLostInTranslation\CallRule;

use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException as PHPStanShouldNotHappenException;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

final class InvalidChoiceRule implements CallRuleInterface
{
    public const IDENTIFIER_MALFORMED = 'lostInTranslation.invalidChoice.malformed';
    public const IDENTIFIER_MISSING_CASE = 'lostInTranslation.invalidChoice.missingCase';
    public const IDENTIFIER_MISSING_PLURAL_FORM = 'lostInTranslation.invalidChoice.missingPluralForm';
    public const IDENTIFIER_NON_NUMERIC = 'lostInTranslation.invalidChoice.nonNumeric';

    /**
     * Positional form counts and exact region suffixes recognized by Laravel's selector; unlisted locales use one.
     *
     * The locale cases are identical across the supported Laravel 9 through 13 range. Matching remains case-sensitive
     * and underscore-sensitive because Laravel's selector switches on the supplied locale without canonicalizing it.
     *
     * @see \Illuminate\Translation\MessageSelector::getPluralIndex()
     * @license https://github.com/laravel/framework/blob/13.x/LICENSE.md
     * @var array<non-empty-string, array{2|3|4|6, list<non-empty-string>}>
     */
    private const PLURAL_FORM_POLICIES = [
        'af' => [2, ['ZA']],
        'am' => [2, ['ET']],
        'ar' => [
            6,
            ['AE', 'BH', 'DZ', 'EG', 'IN', 'IQ', 'JO', 'KW', 'LB', 'LY', 'MA', 'OM', 'QA', 'SA', 'SD', 'SS', 'SY', 'TN', 'YE'],
        ],
        'be' => [3, ['BY']],
        'bg' => [2, ['BG']],
        'bh' => [2, []],
        'bn' => [2, ['BD', 'IN']],
        'bs' => [3, ['BA']],
        'ca' => [2, ['AD', 'ES', 'FR', 'IT']],
        'cs' => [3, ['CZ']],
        'cy' => [4, ['GB']],
        'da' => [2, ['DK']],
        'de' => [2, ['AT', 'BE', 'CH', 'DE', 'LI', 'LU']],
        'el' => [2, ['CY', 'GR']],
        'en' => [
            2,
            ['AG', 'AU', 'BW', 'CA', 'DK', 'GB', 'HK', 'IE', 'IN', 'NG', 'NZ', 'PH', 'SG', 'US', 'ZA', 'ZM', 'ZW'],
        ],
        'eo' => [2, ['US']],
        'es' => [
            2,
            ['AR', 'BO', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'ES', 'GT', 'HN', 'MX', 'NI', 'PA', 'PE', 'PR', 'PY', 'SV', 'US', 'UY', 'VE'],
        ],
        'et' => [2, ['EE']],
        'eu' => [2, ['ES', 'FR']],
        'fa' => [2, ['IR']],
        'fi' => [2, ['FI']],
        'fil' => [2, ['PH']],
        'fo' => [2, ['FO']],
        'fr' => [2, ['BE', 'CA', 'CH', 'FR', 'LU']],
        'fur' => [2, ['IT']],
        'fy' => [2, ['DE', 'NL']],
        'ga' => [3, ['IE']],
        'gl' => [2, ['ES']],
        'gu' => [2, ['IN']],
        'gun' => [2, []],
        'ha' => [2, ['NG']],
        'he' => [2, ['IL']],
        'hi' => [2, ['IN']],
        'hr' => [3, ['HR']],
        'hu' => [2, ['HU']],
        'hy' => [2, ['AM']],
        'is' => [2, ['IS']],
        'it' => [2, ['CH', 'IT']],
        'ku' => [2, ['TR']],
        'lb' => [2, ['LU']],
        'ln' => [2, ['CD']],
        'lt' => [3, ['LT']],
        'lv' => [3, ['LV']],
        'mg' => [2, ['MG']],
        'mk' => [2, ['MK']],
        'ml' => [2, ['IN']],
        'mn' => [2, ['MN']],
        'mr' => [2, ['IN']],
        'mt' => [4, ['MT']],
        'nah' => [2, []],
        'nb' => [2, ['NO']],
        'ne' => [2, ['NP']],
        'nl' => [2, ['AW', 'BE', 'NL']],
        'nn' => [2, ['NO']],
        'no' => [2, []],
        'nso' => [2, ['ZA']],
        'om' => [2, ['ET', 'KE']],
        'or' => [2, ['IN']],
        'pa' => [2, ['IN', 'PK']],
        'pap' => [2, ['AN', 'AW', 'CW']],
        'pl' => [3, ['PL']],
        'ps' => [2, ['AF']],
        'pt' => [2, ['BR', 'PT']],
        'ro' => [3, ['RO']],
        'ru' => [3, ['RU', 'UA']],
        'sk' => [3, ['SK']],
        'sl' => [4, ['SI']],
        'so' => [2, ['DJ', 'ET', 'KE', 'SO']],
        'sq' => [2, ['AL', 'MK']],
        'sr' => [3, ['ME', 'RS']],
        'sv' => [2, ['FI', 'SE']],
        'sw' => [2, ['KE', 'TZ']],
        'ta' => [2, ['IN', 'LK']],
        'te' => [2, ['IN']],
        'ti' => [2, ['ER', 'ET']],
        'tk' => [2, ['TM']],
        'uk' => [3, ['UA']],
        'ur' => [2, ['IN', 'PK']],
        'wa' => [2, ['BE']],
        'xbr' => [2, []],
        'zu' => [2, ['ZA']],
    ];

    /**
     * @logion [RAS 1:1] I beheld the red moon descend behind the glass mountains, yet its reflection remained above
     *     them. The pilgrims knelt before the brighter image, but the eldest broke the frozen lake with her staff; and
     *     from the dark water rose the true moon, bearing every forgotten season upon its face.
     */
    public function __construct(
        private readonly bool $requireCompleteChoiceCoverage = true,
        private readonly bool $requireCompletePluralForms = false,
        private readonly ?TranslationLoader $translationLoader = null,
    ) {
    }

    public function processCall(TranslationCall $call): array
    {
        $errors = [];

        foreach ($call->possibleTranslations as $key => $items) {
            foreach ($items as [$locale, $value]) {
                if (null === $value && MissingTranslationStringInBaseLocaleRule::isLikelyUntranslated($key)) {
                    continue;
                }

                $errors = array_merge(
                    $errors,
                    $this->analyzeChoices($call, $locale, $key, $value ?? $key),
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     * @throws PHPStanShouldNotHappenException
     * @see MessageSelector::choose()
     */
    private function analyzeChoices(TranslationCall $call, string $locale, string $key, string $value): array
    {
        $numberType = $call->numberType;

        if (null === $numberType) {
            return [];
        }

        // Laravel counts arrays and Countable objects before passing the number to its message selector.
        $countableType = new ObjectType(\Countable::class);
        $normalizedNumberTypes = [];
        $discreteNumberTypes = [];
        /** @var list<ConstantFloatType> $constantFloatNumberTypes */
        $constantFloatNumberTypes = [];
        $hasGeneralFloatNumberType = false;

        foreach ($numberType instanceof UnionType ? $numberType->getTypes() : [$numberType] as $candidateType) {
            if ($candidateType->isArray()->yes()) {
                $arraySizeType = $candidateType->getArraySize();
                $normalizedNumberTypes[] = $arraySizeType;
                $discreteNumberTypes[] = $arraySizeType;
                continue;
            }

            if ($countableType->isSuperTypeOf($candidateType)->yes()) {
                $countType = IntegerRangeType::fromInterval(0, null);
                $normalizedNumberTypes[] = $countType;
                $discreteNumberTypes[] = $countType;
                continue;
            }

            if ($candidateType->isInteger()->yes()) {
                $normalizedNumberTypes[] = $candidateType;
                $discreteNumberTypes[] = $candidateType;
                continue;
            }

            if ($candidateType->isFloat()->yes()) {
                $normalizedNumberTypes[] = $candidateType;

                if ($candidateType instanceof ConstantFloatType) {
                    $discreteNumberTypes[] = $candidateType;
                    $constantFloatNumberTypes[] = $candidateType;
                } else {
                    $hasGeneralFloatNumberType = true;
                }
            }
        }

        $hasSupportedNumberType = [] !== $normalizedNumberTypes;
        $numberType = $hasSupportedNumberType
            ? TypeCombinator::union(...$normalizedNumberTypes)
            : new NeverType();
        $hasDiscreteNumberType = [] !== $discreteNumberTypes;
        $discreteNumberType = $hasDiscreteNumberType
            ? TypeCombinator::union(...$discreteNumberTypes)
            : new NeverType();

        $segments = explode('|', $value);
        $errors = [];
        $unionType = null;
        $hasUnconditionedSegment = false;
        $hasInvalidCondition = false;
        $hasNumericCondition = false;
        $hasUniversalNumericCondition = false;
        /** @var list<array{int|float|null, int|float|null}> $numericConditions */
        $numericConditions = [];
        // Laravel compares in-range integer strings as integers, so preserve them before any binary64 conversion.
        $parseIntegerBound = static function (string $bound): ?int {
            $normalizedBound = preg_replace('/^([+-]?)0+(?=\d)/', '$1', trim($bound));

            if (null === $normalizedBound) {
                return null;
            }

            $integer = filter_var($normalizedBound, FILTER_VALIDATE_INT);

            return false === $integer ? null : $integer;
        };

        foreach ($segments as $segment) {
            if (1 !== preg_match('/^([\{\[])([^\[\]\{\}]*)([\}\]])(.*)/s', $segment, $matches, PREG_UNMATCHED_AS_NULL)) {
                if (1 !== preg_match('~^[\[{]~', ltrim($segment))) {
                    // Laravel accepts one or more unconditioned segments and selects one using the locale's plural index.
                    $hasUnconditionedSegment = true;
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf('Failed to parse translation choice: %s', Utils::e($segment)))
                    ->identifier(self::IDENTIFIER_MALFORMED)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                $hasInvalidCondition = true;
                continue;
            }

            /** this may have been failing due to weird return value of preg_match, probably fixed */
            /** @phpstan-ignore-next-line smaller.alwaysFalse */
            assert(count($matches) >= 4);

            [, $openingDelimiter, $condition, $closingDelimiter] = $matches;
            $conditionExpression = sprintf('%s%s%s', $openingDelimiter, $condition, $closingDelimiter);

            $bounds = explode(',', $condition);

            if (count($bounds) > 2) {
                $message = sprintf(
                    'Translation choice range must contain exactly two bounds: %s',
                    Utils::e($conditionExpression),
                );

                if (
                    array_reduce(
                        $bounds,
                        static fn(
                            bool $numeric,
                            string $bound,
                        ): bool => $numeric && 1 === preg_match('/^-?\d+$/D', trim($bound)),
                        true,
                    )
                ) {
                    $integers = array_map(static fn(string $bound): int => (int) trim($bound), $bounds);
                    $isContiguous = true;

                    for ($index = 1, $count = count($integers); $index < $count; ++$index) {
                        if ($integers[$index] !== $integers[$index - 1] + 1) {
                            $isContiguous = false;
                            break;
                        }
                    }

                    if ($isContiguous) {
                        $suggestedRange = sprintf(
                            '%s%d,%d%s',
                            $openingDelimiter,
                            $integers[0],
                            $integers[count($integers) - 1],
                            $closingDelimiter,
                        );
                        $message = sprintf(
                            'Translation choice range must contain exactly two bounds; use %s instead of %s for contiguous values',
                            Utils::e($suggestedRange),
                            Utils::e($conditionExpression),
                        );
                    }
                }

                $errors[] = RuleErrorBuilder::message($message)
                    // Preserve the identifier emitted for this syntax before the message was made more specific.
                    ->identifier(self::IDENTIFIER_NON_NUMERIC)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                $hasInvalidCondition = true;
                continue;
            }

            $isRange = 2 === count($bounds);

            if ($isRange) {
                [$from, $to] = $bounds;
            } else {
                $from = $to = $condition;
            }

            if (!is_numeric($from) && $from !== '*') {
                $errors[] = RuleErrorBuilder::message(sprintf('Translation choice has non-numeric value: %s', Utils::e($from)))
                    ->identifier(self::IDENTIFIER_NON_NUMERIC)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                $hasInvalidCondition = true;
                continue;
            } elseif (!is_numeric($to) && $to !== '*') {
                $errors[] = RuleErrorBuilder::message(sprintf('Translation choice has non-numeric value: %s', Utils::e($to)))
                    ->identifier(self::IDENTIFIER_NON_NUMERIC)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                $hasInvalidCondition = true;
                continue;
            }

            $hasNumericCondition = true;

            // Laravel treats a lone {*} as an exact comparison with "*", which never matches a numeric count.
            if (!$isRange && '*' === $from) {
                continue;
            }

            $integerFromBound = '*' === $from ? null : $parseIntegerBound($from);
            $integerToBound = '*' === $to ? null : $parseIntegerBound($to);
            $numericFrom = '*' === $from ? null : ($integerFromBound ?? (float) $from);
            $numericTo = '*' === $to ? null : ($integerToBound ?? (float) $to);
            $numericConditions[] = [$numericFrom, $numericTo];
            $hasUniversalNumericCondition = $hasUniversalNumericCondition
                || ($isRange && null === $numericFrom && null === $numericTo);

            $integerRangeExists = true;
            $integerFrom = null;
            $integerTo = null;

            if (null !== $numericFrom) {
                if (is_int($numericFrom)) {
                    $integerFrom = $numericFrom;
                } elseif (!is_finite($numericFrom)) {
                    $integerRangeExists = $numericFrom < 0;
                } elseif ($numericFrom >= (float) PHP_INT_MAX) {
                    $integerRangeExists = false;
                } elseif ($numericFrom > (float) PHP_INT_MIN) {
                    $integerFrom = (int) ceil($numericFrom);
                }
            }

            if (null !== $numericTo) {
                if (is_int($numericTo)) {
                    $integerTo = $numericTo;
                } elseif (!is_finite($numericTo)) {
                    $integerRangeExists = $integerRangeExists && $numericTo > 0;
                } elseif ($numericTo < (float) PHP_INT_MIN) {
                    $integerRangeExists = false;
                } elseif ($numericTo < (float) PHP_INT_MAX) {
                    $integerTo = (int) floor($numericTo);
                }
            }

            if (
                $integerRangeExists
                && (null === $integerFrom || null === $integerTo || $integerFrom <= $integerTo)
            ) {
                $segmentType = IntegerRangeType::fromInterval($integerFrom, $integerTo);

                if (null === $unionType) {
                    $unionType = $segmentType;
                } else {
                    $unionType = TypeCombinator::union($unionType, $segmentType);
                }
            }
        }

        if (!$hasInvalidCondition && [] !== $numericConditions) {
            foreach ($constantFloatNumberTypes as $constantNumberType) {
                $number = $constantNumberType->getValue();

                foreach ($numericConditions as [$numericFrom, $numericTo]) {
                    if (
                        (null === $numericFrom || $number >= $numericFrom)
                        && (null === $numericTo || $number <= $numericTo)
                    ) {
                        $unionType = null === $unionType
                            ? $constantNumberType
                            : TypeCombinator::union($unionType, $constantNumberType);
                        break;
                    }
                }
            }
        }

        $hasCompleteFloatCoverage = !$hasGeneralFloatNumberType;

        if ($hasGeneralFloatNumberType && !$hasInvalidCondition && [] !== $numericConditions) {
            /** @var list<array{float, float}> $floatConditions */
            $floatConditions = [];

            foreach ($numericConditions as [$numericFrom, $numericTo]) {
                // Float coverage follows PHP's binary64 comparisons. The integer projection above
                // retains exact integral bounds for the discrete count domain.
                $floatFrom = null === $numericFrom ? -INF : (float) $numericFrom;
                $floatTo = null === $numericTo ? INF : (float) $numericTo;
                $floatConditions[] = [$floatFrom, $floatTo];
            }

            usort(
                $floatConditions,
                static fn (array $left, array $right): int => $left[0] <=> $right[0],
            );

            $coveredTo = -INF;

            foreach ($floatConditions as [$floatFrom, $floatTo]) {
                if ($floatFrom <= $coveredTo) {
                    $coveredTo = max($coveredTo, $floatTo);
                }
            }

            $hasCompleteFloatCoverage = INF === $coveredTo;
        }

        $hasIncompleteDiscreteCoverage = $hasDiscreteNumberType
            && (
                null === $unionType
                || (
                    !$unionType->accepts($discreteNumberType, true)->yes()
                    && !(TypeCombinator::remove($discreteNumberType, $unionType) instanceof NeverType)
                )
            );

        if (
            $this->requireCompleteChoiceCoverage
            && !$hasUnconditionedSegment
            && !$hasInvalidCondition
            && !$hasUniversalNumericCondition
            && $hasNumericCondition
            && $hasSupportedNumberType
            && ($hasIncompleteDiscreteCoverage || !$hasCompleteFloatCoverage)
        ) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Explicit translation choice conditions do not cover all possible cases for number of type: %s',
                $numberType->describe(VerbosityLevel::precise()),
            ))
                ->identifier(self::IDENTIFIER_MISSING_CASE)
                ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                ->line($call->line)
                ->file($call->file)
                ->build();
        }

        if ($this->requireCompletePluralForms && $hasUnconditionedSegment && !$hasInvalidCondition) {
            $pluralLocale = $this->translationLoader?->resolveValidationLocale($locale) ?? $locale;
            [$language, $region] = array_pad(explode('_', $pluralLocale, 2), 2, null);
            $pluralPolicy = self::PLURAL_FORM_POLICIES[$language] ?? null;
            $requiredFormCount = 1;

            if (null !== $pluralPolicy && (null === $region || in_array($region, $pluralPolicy[1], true))) {
                $requiredFormCount = $pluralPolicy[0];
            }

            $providedFormCount = count($segments);

            if ($providedFormCount < $requiredFormCount) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Translation choice provides %d plural %s, but locale %s can select %d forms',
                    $providedFormCount,
                    1 === $providedFormCount ? 'form' : 'forms',
                    Utils::e($locale),
                    $requiredFormCount,
                ))
                    ->identifier(self::IDENTIFIER_MISSING_PLURAL_FORM)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }
        }

        return $errors;
    }
}
