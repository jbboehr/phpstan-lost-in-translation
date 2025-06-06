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

use jbboehr\PHPStanLostInTranslation\Fuzzy\FuzzyStringSetFactory;
use jbboehr\PHPStanLostInTranslation\Fuzzy\FuzzyStringSetInterface;
use jbboehr\PHPStanLostInTranslation\Fuzzy\NaiveFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\Fuzzy\NullFuzzyStringSet;
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
 *     candidate: ?string
 * }
 */
class TranslationLoader
{
    public const IDENTIFIER_CONFLICT = 'lostInTranslation.translationLoaderError.conflictingKey';

    private readonly string $langPath;

    /** @var array<non-empty-string, array<non-empty-string, array<non-empty-string, non-empty-string>>> */
    private array $data = [];

    /** @var list<IdentifierRuleError> */
    private array $errors = [];

    /** @var list<non-empty-string> */
    private array $foundLocales = [];

    /** @var array<non-empty-string, non-empty-list<string>> */
    private array $localeFiles = [];

    /** @var array<string, array{string, int}> */
    private array $locations = [];

    private readonly string $baseLocale;

    private readonly FuzzyStringSetFactory $fuzzyStringSetFactory;

    private readonly FuzzyStringSetInterface $searchDatabase;

    /** @var array<non-empty-string, array{non-empty-string, non-empty-string}>  */
    private array $parsed = [];

    public function __construct(
        ?string $langPath = null,
        ?string $baseLocale = null,
        bool $fuzzySearch = true,
        private readonly PhpLoader $phpLoader = new PhpLoader(),
        private readonly JsonLoader $jsonLoader = new JsonLoader(),
        ?FuzzyStringSetFactory $fuzzyStringSetFactory = null,
    ) {
        $this->langPath = realpath($langPath ?? Utils::detectLangPath()) ?: Utils::detectLangPath();
        $this->baseLocale = $baseLocale ?? Utils::detectBaseLocale();

        if (!$fuzzySearch) {
            $this->fuzzyStringSetFactory = new FuzzyStringSetFactory(NullFuzzyStringSet::class, false);
        } else {
            $this->fuzzyStringSetFactory = $fuzzyStringSetFactory ?? new FuzzyStringSetFactory(NaiveFuzzyStringSet::class, true);
        }

        $this->scan();

        $this->searchDatabase = $this->buildSearchDatabase();
    }

    /**
     * @param non-empty-string $key
     * @param non-empty-string $locale
     * @param non-empty-string $value
     * @internal
     */
    public function add(string $locale, string $key, string $value): void
    {
        [$namespace, $key] = $this->parseKey($key);

        if (strlen($key) <= 0) {
            return;
        }

        $this->data[$locale][$namespace][$key] = $value;

        $this->searchDatabase->addMany([$key, $value]);
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

        if (strlen($key) <= 0) {
            return null;
        }

        return $this->data[$locale][$namespace][$key] ?? null;
    }

    /**
     * @param non-empty-string $key
     */
    public function searchForSimilarKeys(string $key): ?string
    {
        return $this->searchDatabase->search($key);
    }

    /**
     * @phpstan-param list<UsedTranslationRecord> $used
     * @phpstan-return list<UsedTranslationRecordWithCandidate>
     */
    public function diffUsed(array $used): array
    {
        $usedByKey = [];
        $sets = [];

        foreach ($used as $item) {
            if (isset($sets[$item->locale])) {
                $set = $sets[$item->locale];
            } else {
                $set = $sets[$item->locale] = $this->fuzzyStringSetFactory->createFuzzyStringSet();
            }

            if (strlen($item->key) > 0) {
                $set->add($item->key);
            }

            $usedByKey[$item->locale][$item->key] = true;
        }

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

                    $candidate = (($sets[$locale] ?? null)?->search($key)) ?? (($sets['*'] ?? null)?->search($key));

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
     * @return array{non-empty-string, string}
     */
    public function parseKey(string $key): array
    {
        if (strlen($key) <= 0) {
            return ['*', ''];
        }

        if (isset($this->parsed[$key])) {
            return $this->parsed[$key];
        }

        if (!str_contains($key, '::')) {
            $segments = self::parseBasicSegments($key);
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
                        ->identifier(self::IDENTIFIER_CONFLICT)
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

    private function buildSearchDatabase(): FuzzyStringSetInterface
    {
        $arr = [];

        foreach ($this->data as $localeItems) {
            foreach ($localeItems as $namespaceItems) {
                foreach ($namespaceItems as $key => $value) {
                    $arr[$key] = true;
                    $arr[$value] = true;
                }
            }
        }

        return $this->fuzzyStringSetFactory->createFuzzyStringSet(array_keys($arr));
    }

    /**
     * @see \Illuminate\Support\NamespacedItemResolver::parseNamespacedSegments()
     * @license https://github.com/laravel/framework/blob/10.x/LICENSE.md
     * @param non-empty-string $key
     * @return array{non-empty-string, non-empty-string, ?non-empty-string}
     */
    private static function parseNamespacedSegments(string $key): array
    {
        [$namespace, $item] = explode('::', $key);

        if (strlen($namespace) <= 0 || strlen($item) <= 0) {
            return ['*', $key, null];
        }

        $groupAndItem = array_slice(
            self::parseBasicSegments($item),
            1,
        );

        return [$namespace, $groupAndItem[0], $groupAndItem[1] ?? null];
    }

    /**
     * @see \Illuminate\Support\NamespacedItemResolver::parseBasicSegments()
     * @license https://github.com/laravel/framework/blob/10.x/LICENSE.md
     * @return array{null, non-empty-string, ?non-empty-string}
     */
    private static function parseBasicSegments(string $key): array
    {
        $dotCount = substr_count($key, '.');

        if ($dotCount <= 0 || ($dotCount === 1 && $key[0] === '.' || $key[-1] === '.')) {
            assert(strlen($key) > 0);

            return [null, $key, null];
        }

        $segments = explode('.', $key);
        $group = $segments[0];

        assert(strlen($group) > 0);

        if (count($segments) <= 1) {
            return [null, $group, null];
        }

        $item = implode('.', array_slice($segments, 1));

        assert(strlen($item) > 0);

        return [null, $group, $item];
    }
}
