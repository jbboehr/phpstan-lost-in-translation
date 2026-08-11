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

namespace jbboehr\PHPStanLostInTranslation;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<Node\Expr\CallLike, list<UsedTranslationRecord|array<string,string>>>
 */
final class UnusedTranslationStringCollector implements Collector
{
    /**
     * Bladestan analyses compiled templates in a derivative DI container, so
     * the queue must be shared by collector instances in the same worker.
     *
     * @phpstan-var list<UsedTranslationRecord>
     */
    private static array $queued = [];

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

            if (count(self::$queued) <= 0) {
                return null;
            }

            $queued = self::$queued;
            self::$queued = [];
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
                self::$queued[] = new UsedTranslationRecord(
                    key: $keyConstantString->getValue(),
                    locale: $possibleLocale,
                    file: $call->file,
                    line: $call->line,
                );
            }
        }
    }
}
