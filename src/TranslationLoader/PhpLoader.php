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

use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\Utils;
use PhpParser\Error;
use PhpParser\NodeTraverser;
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

    /**
     * @return LoadResult
     */
    public function load(SplFileInfo $file): mixed
    {
        $errors = [];
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

        $raw = self::dot($raw, $group);

        /** @var array<non-empty-string, non-empty-string> $results */
        $results = [];

        foreach ($raw as $k => $v) {
            $line = $lineNumbers[$k] ?? -1;

            if (!is_string($v)) {
                $errors[] = RuleErrorBuilder::message(sprintf("Invalid value: %s", json_encode($v, JSON_THROW_ON_ERROR)))
                    ->identifier(self::IDENTIFIER)
                    ->file($file->getPathname())
                    ->line($line)
                    ->build();
                continue;
            }

            // discard empty keys and values
            if (strlen($k) <= 0 || strlen($v) <= 0) {
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


        return new LoadResult($results, $lineNumbers, $errors);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     * @see \Illuminate\Support\Arr::dot()
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
