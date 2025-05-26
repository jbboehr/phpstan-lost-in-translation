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

use Illuminate\Foundation\Application;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class MissingTranslationStringInBaseLocaleRule implements Rule
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
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
