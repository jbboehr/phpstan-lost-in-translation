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

namespace jbboehr\PHPStanLostInTranslation\Rule;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleInterface;
use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleTrait;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use PhpParser\Node;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class LostInTranslationRule implements Rule, CallRuleInterface
{
    use CallRuleTrait;

    public function __construct(
        LostInTranslationHelper $helper,
        private readonly CallRuleCollection $rules,
    ) {
        $this->helper = $helper;
    }

    public function processCall(TranslationCall $call): array
    {
        $errors = [];

        foreach ($this->rules as $rule) {
            $errors = array_merge(
                $errors,
                $rule->processCall($call),
            );
        }

        return $errors;
    }
}
