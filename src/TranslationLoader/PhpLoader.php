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

use Illuminate\Support\Arr;
use jbboehr\PHPStanLostInTranslation\Utils;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\SplFileInfo;

final class PhpLoader
{
    public function __construct(
        private readonly ?ParserFactory $parserFactory = null,
    ) {
    }

    /**
     * @return LoadResult
     */
    public function load(SplFileInfo $file): mixed
    {
        $warnings = [];
        $group = basename($file->getFilenameWithoutExtension());

        try {
            $parserFactory = $this->parserFactory ?? new ParserFactory();
            $parser = $parserFactory->createForHostVersion();
            $stmts = $parser->parse($file->getContents());
            assert($stmts !== null);

            $visitor = new KeyLineNumberVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);
            $lineNumbers = $visitor->getLineNumbers();
        } catch (Error $e) {
            $warnings[] = [
                sprintf('Failed to parse file with error: %s', $e->getMessage()),
                $file->getPathname(),
                $e->getStartLine(),
            ];
            return new LoadResult([], [], $warnings);
        }

        $raw = (static function (string $__): mixed {
            return require $__;
        })($file->getPathname());

        if (!is_array($raw)) {
            $warnings[] = [
                sprintf('Invalid data type "%s"', gettype($raw)),
                $file->getPathname(),
                -1,
            ];
            return new LoadResult([], [], $warnings);
        }

        $lineNumbers = self::dot($lineNumbers, $group);
        /** @var array<string, int> $lineNumbers */

        $raw = self::dot($raw, $group);

        /** @var array<string, string> $results */
        $results = [];

        foreach ($raw as $k => $v) {
            $line = $lineNumbers[$k] ?? -1;

            if (!is_string($v)) {
                $warnings[] = [
                    sprintf("Invalid value: %s", json_encode($v, JSON_THROW_ON_ERROR)),
                    $file->getPathname(),
                    $line,
                ];
                continue;
            }

            $results[$k] = $v;
        }


        return new LoadResult($results, $lineNumbers, $warnings);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     * @see Arr::dot()
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if ('' === $prepend) {
                $path = (string) $key;
            } elseif (is_int($key)) {
                $path = sprintf("%s.%d", $prepend, $key);
            } else {
                $path = $prepend . '.' . $key;
            }

            if (is_array($value) && ! empty($value)) {
                foreach (self::dot($value, $path) as $k2 => $v2) {
                    $results[$k2] = $v2;
                }
            } else {
                $results[$path] = $value;
            }
        }

        return $results;
    }
}
