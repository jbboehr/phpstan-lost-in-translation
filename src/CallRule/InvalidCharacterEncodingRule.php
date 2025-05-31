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
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\RuleErrorBuilder;

final class InvalidCharacterEncodingRule implements CallRuleInterface
{
    public function processCall(TranslationCall $call): array
    {
        $errors = [];

        foreach ($call->possibleTranslations as $key => $items) {
            if (!mb_check_encoding($key, 'UTF-8')) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Invalid character encoding for key %s',
                    Utils::e($key),
                ))
                    ->identifier('lostInTranslation.invalidCharacterEncoding')
                    ->metadata(Utils::callToMetadata($call, ['lit::key' => $key]))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }

            foreach ($items as [$locale, $value]) {
                if ($value !== null && !mb_check_encoding($value, 'UTF-8')) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Invalid character encoding for value %s in locale %s',
                        Utils::e($key),
                        Utils::e($locale),
                    ))
                        ->identifier('lostInTranslation.invalidCharacterEncoding')
                        ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                        ->line($call->line)
                        ->file($call->file)
                        ->build();
                }
            }
        }

        return $errors;
    }
}
