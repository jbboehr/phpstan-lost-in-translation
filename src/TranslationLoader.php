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
use Illuminate\Support\NamespacedItemResolver;
use Illuminate\Translation\Translator;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class TranslationLoader
{
    private readonly string $langPath;

    private readonly NamespacedItemResolver $namespacedItemResolver;

    /** @var array<string, array<string, array<string, array<string, string>>>> */
    private array $data = [];

    /** @var array<string, array<string, array<string, array<string, bool>>>> */
    private array $used = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $foundLocales = [];

    /** @var array<string, array{string, int}> */
    private array $locations = [];

    private readonly ?string $baseLocale;

    public function __construct(
        ?string $langPath = null,
        ?string $baseLocale = null,
        private readonly ?ParserFactory $parserFactory = null,
    ) {
        if ($langPath === null) {
            $langPath = Utils::detectLangPath();
        }

        $this->langPath = realpath($langPath) ?: $langPath;

        if (null === $baseLocale && class_exists(Application::class, false)) {
            $baseLocale = Application::getInstance()->currentLocale();
        }

        $this->baseLocale = $baseLocale;
        $this->namespacedItemResolver = new NamespacedItemResolver();

        $this->scan();
    }

    public function getBaseLocale(): ?string
    {
        return $this->baseLocale;
    }

    /**
     * @return list<string>
     */
    public function getFoundLocales(): array
    {
        return $this->foundLocales;
    }

    public function get(string $locale, string $key): ?string
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        return $this->data[$locale][$namespace][$group][$item] ?? null;
    }

    public function markUsed(string $locale, string $key): void
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $this->used[$locale][$namespace][$group][$item] = true;
    }

    /**
     * @return list<array{string, string, string, int}>
     */
    public function diffUsed(): array
    {
        $possiblyUnused = [];

        foreach ($this->data as $locale => $t1) {
            foreach ($t1 as $namespace => $t2) {
                foreach ($t2 as $group => $t3) {
                    foreach ($t3 as $item => $flag) {
                        if (
                            !isset($this->used[$locale][$namespace][$group][$item]) &&
                            !isset($this->used['*'][$namespace][$group][$item])
                        ) {
                            $buf = $item;

                            if ($group !== '*') {
                                $buf = $group . '.' . $buf;
                            }

                            if ($namespace !== '*') {
                                $buf = $namespace . '::' . $buf;
                            }

                            [$f, $l] = $this->locations[$locale . "\0" . $namespace . "\0" . $group . "\0" . $item] ?? ['unknown', -1];

                            $possiblyUnused[] = [$locale, $buf, $f, $l];
                        }
                    }
                }
            }
        }

        usort($possiblyUnused, function ($dat) {
            // @TODO make this faster
            return strnatcasecmp(join(', ', $dat), join(',', $dat));
        });

        return $possiblyUnused;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @see Translator::parseKey()
     * @return array{string, string, ?string}
     */
    public function parseKey(string $key): array
    {
        /** @var array{?string, string, ?string} $segments */
        $segments = $this->namespacedItemResolver->parseKey($key);

        if (is_null($segments[0])) {
            $segments[0] = '*';
        }

        if (is_null($segments[2])) {
            $segments[2] = $segments[1];
            $segments[1] = '*';
        }

        return $segments;
    }

    private function scan(): void
    {
        $finder = Finder::create()
            ->in($this->langPath)
            ->name(['*.php', '*.json']);

        $foundLocales = [];

        foreach ($finder as $file) {
            if (
                false === preg_match(
                    '~^([\w-]{2,})(?:\.json|/([^/]+)\.php)$~',
                    $file->getRelativePathname(),
                    $matches,
                    PREG_UNMATCHED_AS_NULL
                )
            ) {
                continue;
            }

            $locale = $matches[1];
            $group = $matches[2] ?? '*';
            $namespace = '*';
            $foundLocales[$locale] = true;

            [$raw, $lineNumbers] = match ($file->getExtension()) {
                'php' => $this->loadPhp($file),
                'json' => $this->loadJson($file),
                default => [null, null],
            };

            if (null === $raw) {
                $this->warnings[] = sprintf("Invalid data type %s in file %s", gettype($raw), $file->getRelativePathname());
                continue;
            }

            foreach ($raw as $k => $v) {
                $this->data[$locale][$namespace][$group][$k] = $v;
                $this->locations[$locale . "\0" . $namespace . "\0" . $group . "\0" . $k] = [
                    $file->getRealPath(),
                    ($lineNumbers[$k] ?? -1),
                ];
            }
        }

        $foundLocales = array_keys($foundLocales);

        // Make sure it is stably sorted
        sort($foundLocales, SORT_NATURAL);

        $this->foundLocales = $foundLocales;
    }

    /**
     * @return array{array<string, string>, array<string, int>}|array{null, null}
     */
    private function loadPhp(SplFileInfo $file): mixed
    {
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
        } catch (\Throwable $e) {
            $this->warnings[] = sprintf('Failed to parse file "%s" with error %s', $file->getRelativePathname(), $e->getMessage());
            $lineNumbers = [];
        }

        $raw = (static function (string $__): mixed {
            return require $__;
        })($file->getPathname());

        if (!is_array($raw)) {
            $this->warnings[] = sprintf('Invalid data type "%s" for file "%s"', gettype($raw), $file->getRelativePathname());
            return [null, null];
        }

        $results = [];

        foreach ($raw as $k => $v) {
            $line = $lineNumbers[$k] ?? -1;

            if (!is_string($k)) {
                $this->warnings[] = sprintf("Invalid key \"%s\" in file %s:%d", print_r($k, true), $file->getRelativePathname(), $line);
                continue;
            }

            if (!is_string($v)) {
                $this->warnings[] = sprintf("Invalid value \"%s\" in file %s:%d", print_r($v, true), $file->getRelativePathname(), $line);
                continue;
            }

            $results[$k] = $v;
        }

        return [$results, $lineNumbers];
    }

    /**
     * @return array{array<string, string>, array<string, int>}|array{null, null}
     */
    private function loadJson(SplFileInfo $file): array
    {
        $buffer = $file->getContents();
        $raw = json_decode($buffer, true, JSON_THROW_ON_ERROR);
        if (!is_array($raw)) {
            $this->warnings[] = sprintf('Invalid data type "%s" for file "%s"', gettype($raw), $file->getRelativePathname());
            return [null, null];
        }

        $results = [];
        $lineNumbers = [];

        foreach ($raw as $k => $v) {
            // this is gross but will probably work most of the time
            try {
                $encoded = json_encode($k, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (false !== ($pos = strpos($buffer, $encoded))) {
                $line = 1 + substr_count($buffer, "\n", 0, $pos);
            } else {
                $line = -1;
            }

            if (!is_string($k)) {
                $this->warnings[] = sprintf("Invalid key \"%s\" in file %s:%d", print_r($k, true), $file->getRelativePathname(), $line);
                continue;
            }

            if (!is_string($v)) {
                $this->warnings[] = sprintf("Invalid value \"%s\" in file %s:%d", print_r($v, true), $file->getRelativePathname(), $line);
                continue;
            }

            $results[$k] = $v;
            $lineNumbers[$k] = $line;
        }

        return [$results, $lineNumbers];
    }
}
