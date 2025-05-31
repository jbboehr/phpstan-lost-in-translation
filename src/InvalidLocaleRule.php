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

namespace jbboehr\PHPStanLostInTranslation;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class InvalidLocaleRule implements Rule, CallRuleInterface
{
    use CallRuleTrait;

    public function __construct(
        LostInTranslationHelper $helper,
        private readonly bool $strictLocales = false,
    ) {
        $this->helper = $helper;
    }

    public function processCall(TranslationCall $call): array
    {
        if (null === $call->localeType) {
            return [];
        }

        $localeType = $call->localeType;
        $errors = [];

        foreach ($localeType->getConstantStrings() as $localeConstantString) {
            $locale = $localeConstantString->getValue();

            if (!$this->helper->hasLocale($locale)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Locale has no available translation strings: %s',
                    $locale,
                ))
                    ->identifier('lostInTranslation.noLocaleTranslations')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale]))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }

            if (!Utils::checkLocaleExists($locale, $this->strictLocales)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Unknown locale: %s',
                    $locale,
                ))
                    ->identifier('lostInTranslation.unknownLocale')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale]))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }
        }

        return $errors;
    }
}
