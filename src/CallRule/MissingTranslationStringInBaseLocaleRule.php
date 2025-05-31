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

use PHPStan\Rules\RuleErrorBuilder;

final class MissingTranslationStringInBaseLocaleRule implements CallRuleInterface
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
    ) {
    }

    public function processCall(TranslationCall $call): array
    {
        $errors = [];
        $baseLocale = $this->helper->getBaseLocale();

        foreach ($call->possibleTranslations as $key => $items) {
            foreach ($items as [$locale, $value]) {
                if ($locale === $baseLocale && null === $value && $this->helper->isLikelyUntranslated($key)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Likely missing translation string %s for base locale: %s',
                        json_encode($key, JSON_THROW_ON_ERROR),
                        $baseLocale
                    ))
                        ->identifier('lostInTranslation.missingBaseLocaleTranslationString')
                        ->metadata(Utils::callToMetadata($call))
                        ->line($call->line)
                        ->file($call->file)
                        ->build();
                }
            }
        }

        return $errors;
    }
}
