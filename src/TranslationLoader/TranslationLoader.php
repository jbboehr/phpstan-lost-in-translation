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

use Fuse\Fuse;
use jbboehr\PHPStanLostInTranslation\UsedTranslationRecord;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Finder\Finder;
use function usort;

/**
 * @final
 * @internal
 * @phpstan-type UsedTranslationRecordWithCandidate array{
 *     key: string,
 *     locale: string,
 *     file: string,
 *     line: int,
 *     candidate: ?array{key: string, locale: string, file: string, line: int}
 * }
 */
class TranslationLoader
{
    private readonly string $langPath;

    /** @var array<string, array<string, array<string, string>>> */
    private array $data = [];

    /** @var list<IdentifierRuleError> */
    private array $errors = [];

    /** @var list<string> */
    private array $foundLocales = [];

    /** @var array<string, non-empty-list<string>> */
    private array $localeFiles = [];

    /** @var array<string, array{string, int}> */
    private array $locations = [];

    private readonly string $baseLocale;

    private readonly Fuse $searchDatabase;

    /** @var array<string, array{string, string}>  */
    private array $parsed = [];

    public function __construct(
        ?string $langPath,
        ?string $baseLocale,
        private readonly PhpLoader $phpLoader,
        private readonly JsonLoader $jsonLoader,
        private readonly float $fuzzySearchThreshold = 0.25,
    ) {
        $this->langPath = realpath($langPath ?? Utils::detectLangPath()) ?: Utils::detectLangPath();
        $this->baseLocale = $baseLocale ?? Utils::detectBaseLocale();

        $this->scan();

        $this->searchDatabase = $this->buildSearchDatabase();
    }

    /**
     * @internal
     */
    public function add(string $locale, string $key, string $value): void
    {
        [$namespace, $key] = $this->parseKey($key);

        $this->data[$locale][$namespace][$key] = $value;

        $this->searchDatabase->add([
            'key' => $key,
            'value' => $value,
            'locale' => $locale,
        ]);
    }


    public function getBaseLocale(): string
    {
        return $this->baseLocale;
    }

    public function hasLocale(string $locale): bool
    {
        return $this->baseLocale === $locale || isset($this->data[$locale]);
    }

    /**
     * @return array<string, non-empty-list<string>>
     */
    public function getLocaleFiles(): array
    {
        return $this->localeFiles;
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
        [$namespace, $key] = $this->parseKey($key);

        return $this->data[$locale][$namespace][$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function searchForSimilarKeys(string $key, ?string $locale = null): array
    {
        $searchResults = $this->searchDatabase->search($key);

        if (null !== $locale) {
            $searchResults = array_filter($searchResults, static function (array $result) use ($locale) {
                return $result['item']['locale'] === $locale;
            });
        }

        $candidates = [];

        foreach ($searchResults as $result) {
            $candidates[$result['item']['key']] = true;
        }

        return array_keys($candidates);
    }

    /**
     * @phpstan-param list<UsedTranslationRecord> $used
     * @phpstan-return list<UsedTranslationRecordWithCandidate>
     */
    public function diffUsed(array $used): array
    {
        $usedByKey = [];

        foreach ($used as $item) {
            $usedByKey[$item->locale][$item->key] = true;
        }

        $searchDatabase = new Fuse(array_map(static function (UsedTranslationRecord $record): array {
            return $record->toArray();
        }, $used), [
            'isCaseSensitive' => true,
            'includeScore' => true,
            'minMatchCharLength' => 2,
            'shouldSort' => true,
            'keys' => ['key'],
            'threshold' => $this->fuzzySearchThreshold,
        ]);

        $possiblyUnused = [];

        foreach ($this->data as $locale => $localeData) {
            foreach ($localeData as $namespace => $namespaceData) {
                foreach ($namespaceData as $item => $value) {
                    $key = $item;

                    if ($namespace !== '*') {
                        $key = $namespace . '::' . $key;
                    }

                    if (isset($usedByKey[$locale][$key]) || isset($usedByKey['*'][$key])) {
                        continue;
                    }

                    $candidate = null;

                    foreach ($searchDatabase->search($key) as $searchResult) {
                        if (!($searchResult['item']['locale'] === $locale || $searchResult['item']['locale'] === '*')) {
                            continue;
                        }

                        $candidate = $searchResult['item'];
                    }

                    [$f, $l] = $this->locations[$locale . "\0" . $namespace . "\0" . $item] ?? ['unknown', -1];

                    $possiblyUnused[] = [
                        'locale' => $locale,
                        'key' => $key,
                        'file' => $f,
                        'line' => $l,
                        'candidate' => $candidate,
                    ];
                }
            }
        }

        usort($possiblyUnused, static function (array $left, array $right) {
            if ($left['locale'] !== $right['locale']) {
                return strnatcasecmp($left['locale'], $right['locale']);
            }

            return strnatcasecmp($left['key'], $right['key']);
        });

        return $possiblyUnused;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @see \Illuminate\Translation\Translator::parseKey()
     * @see \Illuminate\Support\NamespacedItemResolver::parseKey()
     * @return array{string, string}
     */
    public function parseKey(string $key): array
    {
        if (isset($this->parsed[$key])) {
            return $this->parsed[$key];
        }

        if (!str_contains($key, '::')) {
            $segments = self::parseBasicSegments(explode('.', $key));
        } else {
            $segments = self::parseNamespacedSegments($key);
        }

        if (is_null($segments[0])) {
            $segments[0] = '*';
        }

        if (is_null($segments[2])) {
            $key = $segments[1];
        } else {
            $key = $segments[1] . '.' . $segments[2];
        }

        return $this->parsed[$key] = [$segments[0], $key];
    }

    private function scan(): void
    {
        $files = Finder::create()
            ->in($this->langPath)
            ->name(['*.php', '*.json']);

        $files = iterator_to_array($files->getIterator());

        usort($files, static function ($a, $b): int {
            $a = $a->getPathname();
            $b = $b->getPathname();

            $asc = mb_substr_count($a, '/', 'UTF-8');
            $bsc = mb_substr_count($b, '/', 'UTF-8');

            if ($asc !== $bsc) {
                return $asc <=> $bsc;
            }

            return strnatcasecmp($a, $b);
        });

        $foundLocales = [];

        foreach ($files as $file) {
            if (
                false === preg_match(
                    '~^([\w-]{2,})(?:\.json|/([^/]+)\.php)$~',
                    $file->getRelativePathname(),
                    $matches,
                    PREG_UNMATCHED_AS_NULL,
                )
            ) {
                continue;
            }

            $locale = $matches[1];
            //$group = $matches[2] ?? null;
            $namespace = '*';
            $foundLocales[$locale] = true;
            $this->localeFiles[$locale][] = $file->getPathname();

            $result = match ($file->getExtension()) {
                'php' => $this->phpLoader->load($file),
                'json' => $this->jsonLoader->load($file),
                default => null,
            };

            if (null === $result) {
                continue;
            }

            $this->errors = array_merge($this->errors, $result->errors);

            foreach ($result->translations as $k => $v) {
                $line = ($result->locations[$k] ?? -1);

                if (isset($this->data[$locale][$namespace][$k])) {
                    $this->errors[] = RuleErrorBuilder::message(sprintf("Conflicting key: %s", Utils::e($k)))
                        ->identifier('lostInTranslation.conflictingTranslationKey')
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();
                }

                $this->data[$locale][$namespace][$k] = $v;
                $this->locations[$locale . "\0" . $namespace . "\0" . $k] = [$file->getRealPath(), $line];
            }
        }

        $foundLocales = array_keys($foundLocales);

        // Make sure it is stably sorted
        sort($foundLocales, SORT_NATURAL);

        $this->foundLocales = $foundLocales;
    }

    private function buildSearchDatabase(): Fuse
    {
        $arr = [];

        foreach ($this->data as $locale => $localeItems) {
            foreach ($localeItems as $namespace => $namespaceItems) {
                foreach ($namespaceItems as $key => $value) {
                    $arr[] = [
                        'locale' => $locale,
                        'namespace' => $namespace,
                        'key' => $key,
                        'value' => $value,
                    ];
                }
            }
        }

        return new Fuse($arr, [
            'isCaseSensitive' => true,
            'includeScore' => true,
            'minMatchCharLength' => 2,
            'shouldSort' => true,
            'keys' => ['key'],
            'threshold' => $this->fuzzySearchThreshold,
        ]);
    }

    /**
     * @see \Illuminate\Support\NamespacedItemResolver::parseNamespacedSegments()
     * @license https://github.com/laravel/framework/blob/10.x/LICENSE.md
     * @return array{string, string, ?string}
     */
    private static function parseNamespacedSegments(string $key): array
    {
        [$namespace, $item] = explode('::', $key);

        $itemSegments = explode('.', $item);

        $groupAndItem = array_slice(
            self::parseBasicSegments($itemSegments),
            1,
        );

        return [$namespace, $groupAndItem[0], $groupAndItem[1] ?? null];
    }

    /**
     * @see \Illuminate\Support\NamespacedItemResolver::parseBasicSegments()
     * @license https://github.com/laravel/framework/blob/10.x/LICENSE.md
     * @param list<string> $segments
     * @return array{null, string, ?string}
     */
    private static function parseBasicSegments(array $segments): array
    {
        $group = $segments[0];

        $item = count($segments) === 1
            ? null
            : implode('.', array_slice($segments, 1));

        return [null, $group, $item];
    }
}
