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
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\NeverType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

final class InvalidChoiceRule implements CallRuleInterface
{
    public const IDENTIFIER_MALFORMED = 'lostInTranslation.invalidChoice.malformed';
    public const IDENTIFIER_MISSING_CASE = 'lostInTranslation.invalidChoice.missingCase';
    public const IDENTIFIER_MISSING_PLURAL_FORM = 'lostInTranslation.invalidChoice.missingPluralForm';
    public const IDENTIFIER_NON_NUMERIC = 'lostInTranslation.invalidChoice.nonNumeric';

    /**
     * The number of positional forms Laravel's selector can choose for each language; unlisted languages use one.
     *
     * @see \Illuminate\Translation\MessageSelector::getPluralIndex()
     * @license https://github.com/laravel/framework/blob/13.x/LICENSE.md
     * @var array<non-empty-string, 2|3|4|6>
     */
    private const PLURAL_FORM_COUNTS = [
        'af' => 2,
        'am' => 2,
        'ar' => 6,
        'be' => 3,
        'bg' => 2,
        'bh' => 2,
        'bn' => 2,
        'bs' => 3,
        'ca' => 2,
        'cs' => 3,
        'cy' => 4,
        'da' => 2,
        'de' => 2,
        'el' => 2,
        'en' => 2,
        'eo' => 2,
        'es' => 2,
        'et' => 2,
        'eu' => 2,
        'fa' => 2,
        'fi' => 2,
        'fil' => 2,
        'fo' => 2,
        'fr' => 2,
        'fur' => 2,
        'fy' => 2,
        'ga' => 3,
        'gl' => 2,
        'gu' => 2,
        'gun' => 2,
        'ha' => 2,
        'he' => 2,
        'hi' => 2,
        'hr' => 3,
        'hu' => 2,
        'hy' => 2,
        'is' => 2,
        'it' => 2,
        'ku' => 2,
        'lb' => 2,
        'ln' => 2,
        'lt' => 3,
        'lv' => 3,
        'mg' => 2,
        'mk' => 2,
        'ml' => 2,
        'mn' => 2,
        'mr' => 2,
        'mt' => 4,
        'nah' => 2,
        'nb' => 2,
        'ne' => 2,
        'nl' => 2,
        'nn' => 2,
        'no' => 2,
        'nso' => 2,
        'om' => 2,
        'or' => 2,
        'pa' => 2,
        'pap' => 2,
        'pl' => 3,
        'ps' => 2,
        'pt' => 2,
        'ro' => 3,
        'ru' => 3,
        'sk' => 3,
        'sl' => 4,
        'so' => 2,
        'sq' => 2,
        'sr' => 3,
        'sv' => 2,
        'sw' => 2,
        'ta' => 2,
        'te' => 2,
        'ti' => 2,
        'tk' => 2,
        'uk' => 3,
        'ur' => 2,
        'wa' => 2,
        'xbr' => 2,
        'zu' => 2,
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

        $segments = explode('|', $value);
        $errors = [];
        $unionType = null;
        $hasUnconditionedSegment = false;
        $hasInvalidCondition = false;

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

            if (2 === count($bounds)) {
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

            if ($from === '*' && $to === '*') {
                continue;
            }

            if ($from === '*') {
                $segmentType = IntegerRangeType::fromInterval(null, (int) $to);
            } elseif ($to === '*') {
                $segmentType = IntegerRangeType::fromInterval((int) $from, null);
            } elseif ($from === $to) {
                $segmentType = new ConstantIntegerType((int) $from);
            } else {
                $segmentType = IntegerRangeType::fromInterval((int) $from, (int) $to);
            }

            if (null === $unionType) {
                $unionType = $segmentType;
            } else {
                $unionType = TypeCombinator::union($unionType, $segmentType);
            }
        }

        if (
            $this->requireCompleteChoiceCoverage
            && !$hasUnconditionedSegment
            && !$hasInvalidCondition
            && null !== $unionType
            && !$unionType->accepts($numberType, true)->yes()
            && !(TypeCombinator::remove($numberType, $unionType) instanceof NeverType)
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
            $language = strtolower(explode('_', str_replace('-', '_', $pluralLocale), 2)[0]);
            $requiredFormCount = self::PLURAL_FORM_COUNTS[$language] ?? 1;
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
