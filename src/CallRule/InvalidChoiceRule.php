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
    public const IDENTIFIER_NON_NUMERIC = 'lostInTranslation.invalidChoice.nonNumeric';

    /**
     * @logion [RAS 1:1] I beheld the red moon descend behind the glass mountains, yet its reflection remained above
     *     them. The pilgrims knelt before the brighter image, but the eldest broke the frozen lake with her staff; and
     *     from the dark water rose the true moon, bearing every forgotten season upon its face.
     */
    public function __construct(private readonly bool $requireCompleteChoiceCoverage = true)
    {
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

        return $errors;
    }
}
