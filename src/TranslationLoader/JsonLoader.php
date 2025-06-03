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

namespace jbboehr\PHPStanLostInTranslation\TranslationLoader;

use JsonStreamingParser\Parser;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Finder\SplFileInfo;

final class JsonLoader
{
    public const IDENTIFIER = 'lostInTranslation.translationLoaderError';

    public function load(SplFileInfo $file): LoadResult
    {
        $errors = [];

        $buffer = $file->getContents();
        try {
            $raw = json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $errors[] = RuleErrorBuilder::message(sprintf('Failed to parse JSON: %s', $e->getMessage()))
                ->identifier(self::IDENTIFIER)
                ->file($file->getPathname())
                ->build();
            return new LoadResult([], [], $errors);
        }

        if (!is_array($raw)) {
            $errors[] = RuleErrorBuilder::message(sprintf('Invalid data type: "%s"', gettype($raw)))
                ->identifier(self::IDENTIFIER)
                ->file($file->getPathname())
                ->build();
            return new LoadResult([], [], $errors);
        }

        try {
            $lineNumbers = $this->buildLineNumberMap($file);
        } catch (\Throwable $e) {
            $errors[] = RuleErrorBuilder::message(sprintf('Failed to get line numbers for JSON file: %s', $e->getMessage()))
                ->identifier(self::IDENTIFIER)
                ->file($file->getPathname())
                ->build();
            $lineNumbers = [];
        }

        $results = [];

        foreach ($raw as $k => $v) {
            $line = $lineNumbers[$k] ?? $lineNumbers["int\0" . $k] ?? -1;

            if (!is_string($k)) {
                $errors[] = RuleErrorBuilder::message(sprintf("Invalid key: %d", $k))
                    ->identifier(self::IDENTIFIER)
                    ->file($file->getPathname())
                    ->line($line)
                    ->build();
                continue;
            }

            if (!is_string($v)) {
                $errors[] = RuleErrorBuilder::message(sprintf("Invalid value: %s", json_encode($v, JSON_THROW_ON_ERROR)))
                    ->identifier(self::IDENTIFIER)
                    ->file($file->getPathname())
                    ->line($line)
                    ->build();
                continue;
            }

            $results[$k] = $v;
        }

        return new LoadResult($results, $lineNumbers, $errors);
    }

    /**
     * @return array<string, int>
     */
    private function buildLineNumberMap(SplFileInfo $file): array
    {
        $fh = fopen($file->getPathname(), 'r');
        if (false === $fh) {
            return [];
        }

        $listener = new StreamingJsonListener();
        $parser = new Parser($fh, $listener);
        $parser->parse();
        return $listener->getLocations();
    }
}
