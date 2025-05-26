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
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class MissingTranslationStringRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            $call = $this->helper->parseCallLike($node, $scope);

            if (null === $call) {
                return [];
            }

            $errors = [];
            $baseLocale = $this->helper->getBaseLocale();

            foreach ($this->helper->gatherPossibleTranslations($call) as $key => $items) {
                $missingInLocales = [];

                foreach ($items as [$locale, $value]) {
                    if (null === $value && $locale !== $baseLocale) {
                        $missingInLocales[] = $locale;
                    }
                }

                if (count($missingInLocales) > 0) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Missing translation string %s for locales: %s',
                        json_encode($key, JSON_THROW_ON_ERROR),
                        join(', ', $missingInLocales)
                    ))
                        ->identifier('lostInTranslation.missingTranslationString')
                        ->metadata(Utils::callToMetadata($call))
                        ->line($call->line)
                        ->file($call->file)
                        ->build();
                }
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
