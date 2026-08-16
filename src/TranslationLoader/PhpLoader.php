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
            $rawLineNumbers = $visitor->getLineNumbers(raw: true);
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

        $validateRawValues = function (
            array $values,
            string $prepend,
            array $rawPath,
        ) use (
            &$validateRawValues,
            &$errors,
            $file,
            $rawLineNumbers,
        ): void {
            foreach ($values as $key => $value) {
                $path = $prepend . '.' . $key;
                $currentRawPath = [...$rawPath, $key];

                if (is_array($value)) {
                    $validateRawValues($value, $path, $currentRawPath);
                    continue;
                }

                $line = $rawLineNumbers[serialize($currentRawPath)] ?? -1;

                if (!is_string($value)) {
                    $encodedValue = json_encode($value);

                    if (false === $encodedValue) {
                        $encodedValue = is_float($value)
                            ? (is_nan($value) ? 'NAN' : (0.0 > $value ? '-INF' : 'INF'))
                            : get_debug_type($value);
                    }

                    $errors[] = RuleErrorBuilder::message(sprintf("Invalid value: %s", $encodedValue))
                        ->identifier(self::IDENTIFIER)
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();
                    continue;
                }

                if ('' === $value || !$this->invalidCharacterEncodings) {
                    continue;
                }

                if (!mb_check_encoding($path, 'UTF-8')) {
                    $errors[] = RuleErrorBuilder::message(sprintf('Invalid character encoding for key: %s', Utils::e($path)))
                        ->identifier(InvalidCharacterEncodingRule::IDENTIFIER)
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();
                }

                if (!mb_check_encoding($value, 'UTF-8')) {
                    $errors[] = RuleErrorBuilder::message(sprintf('Invalid character encoding for value: %s', Utils::e($value)))
                        ->identifier(InvalidCharacterEncodingRule::IDENTIFIER)
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();
                }
            }
        };
        $validateRawValues($raw, $group, []);

        $flattened = self::dot($raw, $group, true);

        if ([] !== $raw) {
            $flattened = [$group => $raw] + $flattened;
        }

        $raw = $flattened;

        /** @var array<array-key, non-empty-string> $results */
        $results = [];
        /** @var list<non-empty-string> $arrayKeys */
        $arrayKeys = [];

        foreach ($raw as $k => $v) {
            $k = (string) $k;
            assert('' !== $k);
            $line = $lineNumbers[$k] ?? -1;

            if (is_array($v)) {
                $arrayKeys[] = $k;
                /** @var list<non-empty-string> $arrayValues */
                $arrayValues = [];
                array_walk_recursive($v, static function (mixed $value) use (&$arrayValues): void {
                    if (is_string($value) && '' !== $value) {
                        $arrayValues[] = $value;
                    }
                });
                $v = [] === $arrayValues ? $k : implode("\n", $arrayValues);
            }

            if (!is_string($v)) {
                continue;
            }

            // discard empty keys and values
            if (strlen($v) <= 0) {
                continue;
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
     * @see \Illuminate\Support\Arr::get()
     */
    public static function dot(
        array $array,
        string $prepend = '',
        bool $includeArrays = false,
        bool $root = true,
    ): array {
        $results = [];
        $exactRootPaths = [];

        if ($root) {
            foreach ($array as $key => $_value) {
                if (!is_string($key) || !str_contains($key, '.')) {
                    continue;
                }

                $path = '' === $prepend ? $key : $prepend . '.' . $key;
                $exactRootPaths[$path] = true;
            }
        }

        foreach ($array as $key => $value) {
            if ('' === $prepend) {
                $path = (string) $key;
            } elseif (is_int($key)) {
                $path = sprintf("%s.%d", $prepend, $key);
            } else {
                $path = $prepend . '.' . $key;
            }

            $literalDottedKey = is_string($key) && str_contains($key, '.');

            // Arr::get() checks an exact dotted key only in the group root, then traverses one segment at a time.
            if (!$root && $literalDottedKey) {
                continue;
            }

            if (is_array($value)) {
                // Empty arrays are structural placeholders in Laravel lang files and must not become invalid leaves.
                if ([] === $value) {
                    continue;
                }

                if ($includeArrays && ($literalDottedKey || !array_key_exists($path, $results))) {
                    $results[$path] = $value;
                }

                // Children of a literal dotted root key are not reachable by adding further dotted segments.
                if ($literalDottedKey) {
                    continue;
                }

                foreach (self::dot($value, $path, $includeArrays, false) as $k2 => $v2) {
                    if (!isset($exactRootPaths[$k2]) && !array_key_exists($k2, $results)) {
                        $results[$k2] = $v2;
                    }
                }
            } else {
                if ($literalDottedKey || (!isset($exactRootPaths[$path]) && !array_key_exists($path, $results))) {
                    $results[$path] = $value;
                }
            }
        }

        return $results;
    }
}
