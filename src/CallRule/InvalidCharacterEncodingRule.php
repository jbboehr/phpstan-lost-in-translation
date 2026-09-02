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

use jbboehr\PHPStanLostInTranslation\Identifier;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @internal
 */
final class InvalidCharacterEncodingRule implements CallRuleInterface
{
    public const IDENTIFIER = Identifier::INVALID_CHARACTER_ENCODING;

    public function processCall(TranslationCall $call): array
    {
        $errors = [];

        foreach ($call->possibleTranslations as $key => $items) {
            if (!mb_check_encoding($key, 'UTF-8')) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Invalid character encoding for key %s',
                    Utils::e($key),
                ))
                    ->identifier(self::IDENTIFIER)
                    ->metadata(Utils::metadata(key: $key))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }

            foreach ($items as [$locale, $value]) {
                if ($value !== null && !mb_check_encoding($value, 'UTF-8')) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Invalid character encoding for value %s in locale %s',
                        Utils::e($value),
                        Utils::e($locale),
                    ))
                        ->identifier(self::IDENTIFIER)
                        ->metadata(Utils::metadata(key: $key, locale: $locale, value: $value))
                        ->line($call->line)
                        ->file($call->file)
                        ->build();
                }
            }
        }

        return $errors;
    }
}
