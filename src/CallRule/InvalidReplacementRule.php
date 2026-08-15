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
use function sort;

final class InvalidReplacementRule implements CallRuleInterface
{
    public const IDENTIFIER_UNUSED = 'lostInTranslation.invalidReplacement.unused';
    public const IDENTIFIER_MULTIPLE_VARIANTS = 'lostInTranslation.invalidReplacement.multipleVariants';

    public function processCall(TranslationCall $call): array
    {
        $errors = [];

        foreach ($call->possibleTranslations as $key => $items) {
            foreach ($items as [$locale, $value]) {
                $errors = array_merge(
                    $errors,
                    $this->analyzeReplacements($call, $locale, $key, $value ?? $key),
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     * @throws PHPStanShouldNotHappenException
     */
    private function analyzeReplacements(TranslationCall $call, string $locale, string $key, string $value): array
    {
        if (null === $call->replaceType) {
            return [];
        }

        /** @see Translator::makeReplacements() */
        $errors = [];

        $replaceKeys = [];
        foreach ($call->replaceType->getConstantArrays() as $constantArray) {
            foreach ($constantArray->getKeyType()->getConstantStrings() as $constantString) {
                $replaceKeys[] = $constantString->getValue();
            }
        }

        $replaceKeys = array_values(array_unique($replaceKeys));

        // Make sure they are stably sorted
        sort($replaceKeys, SORT_NATURAL);

        foreach ($replaceKeys as $search) {
            $replaceVariants = array_unique([
                ':' . self::ucfirst($search),
                ':' . mb_strtoupper($search, 'UTF-8'),
                ':' . $search,
            ]);
            $replaceVariantCount = 0;

            foreach ($replaceVariants as $replaceVariant) {
                $replaceVariantCount += (int) str_contains($value, $replaceVariant);
            }

            if ($replaceVariantCount === 0) {
                $errors[] = RuleErrorBuilder::message(sprintf('Unused translation replacement: %s', Utils::e($search)))
                    ->identifier(self::IDENTIFIER_UNUSED)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            } elseif ($replaceVariantCount > 1) {
                $errors[] = RuleErrorBuilder::message(sprintf('Replacement string matches multiple variants: %s', Utils::e($search)))
                    ->identifier(self::IDENTIFIER_MULTIPLE_VARIANTS)
                    ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @see \Illuminate\Support\Str::ucfirst()
     */
    private static function ucfirst(string $search): string
    {
        return mb_strtoupper(mb_substr($search, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($search, 1, null, 'UTF-8');
    }
}
