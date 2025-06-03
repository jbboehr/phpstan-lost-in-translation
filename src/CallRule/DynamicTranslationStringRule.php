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
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

final class DynamicTranslationStringRule implements CallRuleInterface
{
    public const IDENTIFIER = 'lostInTranslation.dynamicTranslationString';

    public function processCall(TranslationCall $call): array
    {
        $errors = [];

        if (count($call->keyType->getConstantStrings()) <= 0) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Disallowed dynamic translation string of type: %s',
                $call->keyType->describe(VerbosityLevel::precise()),
            ))
                ->identifier(self::IDENTIFIER)
                ->line($call->line)
                ->file($call->file)
                ->build();
        }

        return $errors;
    }
}
