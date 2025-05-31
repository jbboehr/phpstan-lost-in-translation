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

namespace jbboehr\PHPStanLostInTranslation\CallRule;

use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

final class InvalidChoiceRule implements CallRuleInterface
{
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

        foreach ($segments as $segment) {
            if (1 !== preg_match('/^[\{\[]([^\[\]\{\}]*)[\}\]](.*)/s', $segment, $matches, PREG_UNMATCHED_AS_NULL)) {
                if (count($segments) === 2 && 1 !== preg_match('~^[\[{]~', ltrim($segment))) {
                    // If it has exactly two segments and doesn't start with "{" or "[", it's probably the singular/plural variant
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf('Failed to parse translation choice: %s', Utils::e($segment)))
                    ->identifier('lostInTranslation.malformedTranslationChoice')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                continue;
            }

            /** this may have been failing due to weird return value of preg_match, probably fixed */
            /** @phpstan-ignore-next-line smaller.alwaysFalse */
            assert(count($matches) >= 2);

            [, $condition] = $matches;

            if (str_contains($condition, ',')) {
                [$from, $to] = explode(',', $condition, 2);
            } else {
                $from = $to = $condition;
            }

            if (!is_numeric($from) && $from !== '*') {
                $errors[] = RuleErrorBuilder::message(sprintf('Translation choice has non-numeric value: %s', Utils::e($from)))
                    ->identifier('lostInTranslation.nonNumericChoice')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                continue;
            } elseif (!is_numeric($to) && $to !== '*') {
                $errors[] = RuleErrorBuilder::message(sprintf('Translation choice has non-numeric value: %s', Utils::e($to)))
                    ->identifier('lostInTranslation.nonNumericChoice')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                continue;
            }

            if ($from === '*' && $to === '*') {
                continue;
            }

            // @TODO might want to add an option to ignore negative numbers, probably will cause a lot of false? positives
            // if ($from === '0') {
            //     $from = '*';
            // }

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

        if (null !== $unionType && !$unionType->accepts($numberType, true)->yes()) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Translation choice does not cover all possible cases for number of type: %s',
                $numberType->describe(VerbosityLevel::precise())
            ))
                ->identifier('lostInTranslation.choiceMissingCase')
                ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                ->line($call->line)
                ->file($call->file)
                ->build();
        }

        return $errors;
    }
}
