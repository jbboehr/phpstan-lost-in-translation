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

namespace jbboehr\PHPStanLostInTranslation\TranslationLoader;

use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\Utils;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Finder\SplFileInfo;

final class PhpLoader
{
    public const IDENTIFIER = 'lostInTranslation.translationLoaderError';

    public function __construct(
        private readonly ?ParserFactory $parserFactory = null,
        private readonly bool $invalidCharacterEncodings = true,
    ) {
    }

    public function load(SplFileInfo $file): LoadResult
    {
        $errors = [];
        $group = basename($file->getFilenameWithoutExtension());

        try {
            $parserFactory = $this->parserFactory ?? new ParserFactory();
            $parser = self::createParser($parserFactory);
            $stmts = $parser->parse($file->getContents());
            assert($stmts !== null);

            $visitor = new KeyLineNumberVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);
            $lineNumbers = $visitor->getLineNumbers();
        } catch (Error $e) {
            $errors[] = RuleErrorBuilder::message(sprintf('Failed to parse file with error: %s', $e->getMessage()))
                ->identifier(self::IDENTIFIER)
                ->file($file->getPathname())
                ->line($e->getStartLine())
                ->build();
            return new LoadResult([], [], $errors);
        }

        $raw = (static function (string $__): mixed {
            return require $__;
        })($file->getPathname());

        if (!is_array($raw)) {
            $errors[] = RuleErrorBuilder::message(sprintf('Invalid data type "%s"', gettype($raw)))
                ->identifier(self::IDENTIFIER)
                ->file($file->getPathname())
                ->line(-1)
                ->build();
            return new LoadResult([], [], $errors);
        }

        $lineNumbers = self::dot($lineNumbers, $group);
        /** @var array<non-empty-string, int> $lineNumbers */

        $raw = self::dot($raw, $group, true);

        /** @var array<non-empty-string, non-empty-string> $results */
        $results = [];
        /** @var list<non-empty-string> $arrayKeys */
        $arrayKeys = [];

        foreach ($raw as $k => $v) {
            assert(is_string($k) && '' !== $k);
            $line = $lineNumbers[$k] ?? -1;

            if (is_array($v)) {
                $arrayKeys[] = $k;
                $arrayValues = array_filter(
                    self::dot($v),
                    static fn (mixed $value): bool => is_string($value) && '' !== $value,
                );
                $v = [] === $arrayValues ? $k : implode("\n", $arrayValues);
            }

            if (!is_string($v)) {
                $encodedValue = json_encode($v);

                if (false === $encodedValue) {
                    $encodedValue = is_float($v)
                        ? (is_nan($v) ? 'NAN' : (0.0 > $v ? '-INF' : 'INF'))
                        : get_debug_type($v);
                }

                $errors[] = RuleErrorBuilder::message(sprintf("Invalid value: %s", $encodedValue))
                    ->identifier(self::IDENTIFIER)
                    ->file($file->getPathname())
                    ->line($line)
                    ->build();
                continue;
            }

            // discard empty keys and values
            if (strlen($v) <= 0) {
                continue;
            }

            if ($this->invalidCharacterEncodings) {
                if (!mb_check_encoding($k, 'UTF-8')) {
                    $errors[] = RuleErrorBuilder::message(sprintf('Invalid character encoding for key: %s', Utils::e($k)))
                        ->identifier(InvalidCharacterEncodingRule::IDENTIFIER)
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();
                }

                if (!mb_check_encoding($v, 'UTF-8')) {
                    $errors[] = RuleErrorBuilder::message(sprintf('Invalid character encoding for value: %s', Utils::e($v)))
                        ->identifier(InvalidCharacterEncodingRule::IDENTIFIER)
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();
                }
            }

            $results[$k] = $v;
        }


        return new LoadResult($results, $lineNumbers, $errors, $arrayKeys);
    }

    private static function createParser(ParserFactory $parserFactory): Parser
    {
        $reflection = new \ReflectionObject($parserFactory);
        $create = $reflection->hasMethod('createForHostVersion')
            ? $reflection->getMethod('createForHostVersion')
            : $reflection->getMethod('create');
        $arguments = 'create' === $create->getName()
            ? [1] // ParserFactory::PREFER_PHP7 in PHP-Parser 4.
            : [];
        $parser = $create->invokeArgs($parserFactory, $arguments);
        assert($parser instanceof Parser);

        return $parser;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     * @see \Illuminate\Support\Arr::dot()
     */
    public static function dot(array $array, string $prepend = '', bool $includeArrays = false): array
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

            if (is_array($value)) {
                // Empty arrays are structural placeholders in Laravel lang files and must not become invalid leaves.
                if ([] === $value) {
                    continue;
                }

                if ($includeArrays && !array_key_exists($path, $results)) {
                    $results[$path] = $value;
                }

                foreach (self::dot($value, $path, $includeArrays) as $k2 => $v2) {
                    // Laravel's Arr::get() gives an exact dotted key precedence over traversal.
                    if (!array_key_exists($k2, $results)) {
                        $results[$k2] = $v2;
                    }
                }
            } else {
                // A literal dotted item remains authoritative regardless of declaration order.
                $results[$path] = $value;
            }
        }

        return $results;
    }
}
