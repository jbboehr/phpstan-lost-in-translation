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
use PHPStan\Collectors\Collector;

/**
 * @phpstan-type UsedTranslationRecord array{key: string, locale: string, file: string, line: int}
 * @implements Collector<Node\Expr\CallLike, list<UsedTranslationRecord>>
 */
final class UnusedTranslationStringCollector implements Collector
{
    /** @phpstan-var list<UsedTranslationRecord> */
    private array $queued = [];

    public function __construct(
        private readonly LostInTranslationHelper $helper,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        try {
            if (str_contains($scope->getFile(), 'blade-compiled')) {
                return null;
            }

            $call = $this->helper->parseCallLike($node, $scope);

            if (null !== $call) {
                $this->push($call);
            }

            if (count($this->queued) <= 0) {
                return null;
            }

            $queued = $this->queued;
            $this->queued = [];
            return $queued;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }

    public function push(TranslationCall $call): void
    {
        if (count($call->keyType->getConstantStrings()) <= 0) {
            return;
        }

        $possibleLocales = [];

        if ($call->localeType !== null) {
            foreach ($call->localeType->getConstantStrings() as $localeConstantString) {
                $possibleLocales[] = $localeConstantString->getValue();
            }
        }

        if (count($possibleLocales) <= 0) {
            $possibleLocales = ['*'];
        }

        foreach ($call->keyType->getConstantStrings() as $keyConstantString) {
            foreach ($possibleLocales as $possibleLocale) {
                $this->queued[] = [
                    'key' => $keyConstantString->getValue(),
                    'locale' => $possibleLocale,
                    'file' => $call->file,
                    'line' => $call->line,
                ];
            }
        }
    }
}
