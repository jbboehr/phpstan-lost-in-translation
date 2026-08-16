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
    public const IDENTIFIER_LOCALE_CONFLICT = 'lostInTranslation.translationLoaderError.conflictingLocale';

    private readonly ?string $langPath;

    /** @var array<string, array<non-empty-string, array<array-key, non-empty-string>>> */
    private array $data = [];

    /** @var array<string, array<non-empty-string, array<array-key, true>>> */
    private array $arrayKeys = [];

    /** @var list<IdentifierRuleError> */
    private array $errors = [];

    /** @var list<non-empty-string> */
    private array $foundLocales = [];

    /** @var list<string> */
    private readonly array $implicitLookupLocales;

    /** @var array<non-empty-string, non-empty-list<string>> */
    private array $localeFiles = [];

    /** @var array<string, non-empty-string> */
    private array $localeNames = [];

    /** @var array<string, non-empty-string> */
    private readonly array $localeAliases;

    /** @var array<string, array<non-empty-string, true>> */
    private array $reportedLocaleConflicts = [];

    /** @var array<string, array{string, int}> */
    private array $locations = [];

    private readonly string $baseLocale;

    private readonly string $baseLocaleKey;

    private readonly FuzzyStringSetFactory $fuzzyStringSetFactory;

    private readonly FuzzyStringSetInterface $searchDatabase;

    /** @var array<non-empty-string, array{non-empty-string, non-empty-string}>  */
    private array $parsed = [];

    /**
     * @param array<array-key, mixed> $localeAliases
     */
    public function __construct(
        ?string $langPath = null,
        ?string $baseLocale = null,
        bool $fuzzySearch = true,
        private readonly PhpLoader $phpLoader = new PhpLoader(),
        private readonly JsonLoader $jsonLoader = new JsonLoader(),
        ?FuzzyStringSetFactory $fuzzyStringSetFactory = null,
        private readonly bool $strictLocales = false,
        array $localeAliases = [],
    ) {
        $normalizedLocaleAliases = [];
        $localeAliasNames = [];

        foreach ($localeAliases as $alias => $target) {
            if (!is_string($alias) || !is_string($target) || '' === $alias || '' === $target) {
                throw new \InvalidArgumentException('Locale aliases and their targets must be non-empty strings');
            }

            if (!Utils::checkLocaleExists($target, $this->strictLocales)) {
                throw new \InvalidArgumentException(sprintf(
                    'Locale alias target %s for %s is not known to Symfony Intl',
                    Utils::e($target),
                    Utils::e($alias),
                ));
            }

            $aliasKey = $this->canonicalizeLocale($alias);

            if (isset($localeAliasNames[$aliasKey])) {
                throw new \InvalidArgumentException(sprintf(
                    'Locale aliases %s and %s both resolve to %s',
                    Utils::e($localeAliasNames[$aliasKey]),
                    Utils::e($alias),
                    Utils::e($aliasKey),
                ));
            }

            $normalizedLocaleAliases[$aliasKey] = $target;
            $localeAliasNames[$aliasKey] = $alias;
        }

        $this->localeAliases = $normalizedLocaleAliases;

        $candidateLangPath = $langPath ?? Utils::detectLangPath();
        $resolvedLangPath = realpath($candidateLangPath);

        if (false === $resolvedLangPath || !is_dir($resolvedLangPath)) {
            if (null !== $langPath) {
                throw new \InvalidArgumentException(sprintf(
                    'Configured language directory %s does not exist or is not a directory',
                    Utils::e($langPath),
                ));
            }

            $this->langPath = null;
        } else {
            $this->langPath = $resolvedLangPath;
        }
        $this->baseLocale = $baseLocale ?? Utils::detectBaseLocale();
        $this->baseLocaleKey = $this->canonicalizeLocale($this->baseLocale);

        if (!$fuzzySearch) {
            $this->fuzzyStringSetFactory = new FuzzyStringSetFactory(NullFuzzyStringSet::class, false);
        } else {
            $this->fuzzyStringSetFactory = $fuzzyStringSetFactory ?? new FuzzyStringSetFactory(NaiveFuzzyStringSet::class, true);
        }

        $this->scan();
        $this->implicitLookupLocales = $this->buildImplicitLookupLocales();

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

        $locale = $this->canonicalizeLocale($locale);
        $this->data[$locale][$namespace][$key] = $value;

        $searchKey = '*' === $namespace ? $key : $namespace . '::' . $key;
        $this->searchDatabase->addMany([$searchKey, $value]);
    }


    public function getBaseLocale(): string
    {
        return $this->baseLocale;
    }

    public function hasLocale(string $locale): bool
    {
        $locale = $this->canonicalizeLocale($locale);

        return $this->baseLocaleKey === $locale || isset($this->data[$locale]);
    }

    public function isBaseLocale(string $locale): bool
    {
        return $this->baseLocaleKey === $this->canonicalizeLocale($locale);
    }

    public function isValidLocale(string $locale): bool
    {
        return Utils::checkLocaleExists($this->resolveValidationLocale($locale), $this->strictLocales);
    }

    /**
     * @logion [RAS 21:85] Above the drowned observatory there appeared a wheel of blue fire, turning against the stars;
     *     and each revolution restored one forgotten constellation while extinguishing a palace below. The astronomers
     *     praised neither loss nor wonder, but covered their instruments, for the heavens had begun to remember what
     *     the empire had chosen to spend.
     */
    public function resolveValidationLocale(string $locale): string
    {
        return $this->localeAliases[$this->canonicalizeLocale($locale)] ?? $locale;
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

    /**
     * @return list<string>
     */
    public function getLocalesForImplicitLookup(): array
    {
        return $this->implicitLookupLocales;
    }

    /**
     * @return list<string>
     */
    private function buildImplicitLookupLocales(): array
    {
        $locales = $this->foundLocales;
        $baseLocaleFound = false;

        foreach ($locales as $locale) {
            if ($this->isBaseLocale($locale)) {
                $baseLocaleFound = true;
                break;
            }
        }

        if (!$baseLocaleFound) {
            $locales[] = $this->baseLocale;
        }

        // Make sure they are stably sorted
        sort($locales, SORT_NATURAL);

        return $locales;
    }

    public function get(string $locale, string $key): ?string
    {
        [$namespace, $key] = $this->parseKey($key);

        if (strlen($key) <= 0) {
            return null;
        }

        $locale = $this->canonicalizeLocale($locale);

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
            $locale = '*' === $item->locale ? '*' : $this->canonicalizeLocale($item->locale);

            if (isset($sets[$locale])) {
                $set = $sets[$locale];
            } else {
                $set = $sets[$locale] = $this->fuzzyStringSetFactory->createFuzzyStringSet();
            }

            if (strlen($item->key) > 0) {
                $set->add($item->key);
            }

            $usedByKey[$locale][$item->key] = true;

            [$namespace, $normalizedKey] = $this->parseKey($item->key);
            $targetLocales = '*' === $locale ? array_keys($this->data) : [$locale];

            foreach ($targetLocales as $targetLocale) {
                if (!isset($this->arrayKeys[$targetLocale][$namespace][$normalizedKey])) {
                    continue;
                }

                foreach ($this->data[$targetLocale][$namespace] ?? [] as $candidateKey => $_value) {
                    $candidateKey = (string) $candidateKey;
                    assert('' !== $candidateKey);

                    if ($candidateKey !== $normalizedKey && !str_starts_with($candidateKey, $normalizedKey . '.')) {
                        continue;
                    }

                    $externalKey = '*' === $namespace ? $candidateKey : $namespace . '::' . $candidateKey;
                    $usedByKey[$targetLocale][$externalKey] = true;
                }
            }
        }

        $possiblyUnused = [];

        foreach ($this->data as $locale => $localeData) {
            foreach ($localeData as $namespace => $namespaceData) {
                foreach ($namespaceData as $item => $value) {
                    $item = (string) $item;
                    assert('' !== $item);

                    if (isset($this->arrayKeys[$locale][$namespace][$item])) {
                        continue;
                    }

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
                        'locale' => $this->localeNames[$locale] ?? $locale,
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

        $cacheKey = $key;

        if (!str_contains($key, '::')) {
            $segments = self::parseBasicSegments($key);
        } else {
            $segments = self::parseNamespacedSegments($key);
        }

        if (is_null($segments[0])) {
            $segments[0] = '*';
        }

        if (is_null($segments[2])) {
            $normalizedKey = $segments[1];
        } else {
            $normalizedKey = $segments[1] . '.' . $segments[2];
        }

        return $this->parsed[$cacheKey] = [$segments[0], $normalizedKey];
    }

    private function scan(): void
    {
        if (null === $this->langPath) {
            return;
        }

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

            $comparison = strnatcasecmp($a, $b);

            return 0 !== $comparison ? $comparison : strcmp($a, $b);
        });

        $foundLocales = [];
        $sourceExtensions = [];

        foreach ($files as $file) {
            $relativePathname = $file->getRelativePathname();

            if (
                1 === preg_match(
                    '~^vendor/([^/]+)/([\w-]{2,})/([^/]+)\.php$~',
                    $relativePathname,
                    $matches,
                )
            ) {
                $namespace = $matches[1];
                $locale = $matches[2];
            } elseif (
                1 === preg_match(
                    '~^([\w-]{2,})(?:\.json|/([^/]+)\.php)$~',
                    $relativePathname,
                    $matches,
                    PREG_UNMATCHED_AS_NULL,
                )
            ) {
                $locale = $matches[1];
                $namespace = '*';
            } else {
                continue;
            }

            $this->localeFiles[$locale][] = $file->getPathname();

            $localeKey = $this->canonicalizeLocale($locale);
            $existingLocale = $this->localeNames[$localeKey] ?? null;

            if (null !== $existingLocale && $existingLocale !== $locale) {
                if (!isset($this->reportedLocaleConflicts[$localeKey][$locale])) {
                    $this->errors[] = RuleErrorBuilder::message(sprintf(
                        'Ignoring translation files for locale %s because it resolves to %s, which is already provided by %s',
                        Utils::e($locale),
                        Utils::e($localeKey),
                        Utils::e($existingLocale),
                    ))
                        ->identifier(self::IDENTIFIER_LOCALE_CONFLICT)
                        ->file($file->getPathname())
                        ->build();
                    $this->reportedLocaleConflicts[$localeKey][$locale] = true;
                }

                continue;
            }

            $this->localeNames[$localeKey] = $locale;
            $foundLocales[$locale] = true;

            $result = match ($file->getExtension()) {
                'php' => $this->phpLoader->load($file),
                'json' => $this->jsonLoader->load($file),
                default => null,
            };

            if (null === $result) {
                continue;
            }

            $this->errors = array_merge($this->errors, $result->errors);
            $realPath = $file->getRealPath();
            $filePath = false === $realPath ? $file->getPathname() : $realPath;
            $resultArrayKeys = array_fill_keys($result->arrayKeys, true);

            foreach ($result->translations as $k => $v) {
                $k = (string) $k;
                $line = ($result->locations[$k] ?? -1);

                if (isset($this->data[$localeKey][$namespace][$k])) {
                    $this->errors[] = RuleErrorBuilder::message(sprintf("Conflicting key: %s", Utils::e($k)))
                        ->identifier(self::IDENTIFIER_CONFLICT)
                        ->file($file->getPathname())
                        ->line($line)
                        ->build();

                    if ('json' === ($sourceExtensions[$localeKey][$namespace][$k] ?? null)) {
                        // Laravel checks the root JSON catalogue before grouped PHP translations.
                        continue;
                    }
                }

                $this->data[$localeKey][$namespace][$k] = $v;
                $this->locations[$localeKey . "\0" . $namespace . "\0" . $k] = [$filePath, $line];
                $sourceExtensions[$localeKey][$namespace][$k] = $file->getExtension();

                if (isset($resultArrayKeys[$k])) {
                    $this->arrayKeys[$localeKey][$namespace][$k] = true;
                } else {
                    unset($this->arrayKeys[$localeKey][$namespace][$k]);
                }
            }
        }

        $foundLocales = array_keys($foundLocales);

        // Make sure it is stably sorted
        sort($foundLocales, SORT_NATURAL);

        $this->foundLocales = $foundLocales;
    }

    private function canonicalizeLocale(string $locale): string
    {
        return Utils::canonicalizeLocale($locale, $this->strictLocales);
    }

    private function buildSearchDatabase(): FuzzyStringSetInterface
    {
        $arr = [];

        foreach ($this->data as $locale => $localeItems) {
            foreach ($localeItems as $namespace => $namespaceItems) {
                foreach ($namespaceItems as $key => $value) {
                    $key = (string) $key;
                    assert('' !== $key);
                    $searchKey = '*' === $namespace ? $key : $namespace . '::' . $key;
                    $arr[$searchKey] = true;

                    if (!isset($this->arrayKeys[$locale][$namespace][$key])) {
                        $arr[$value] = true;
                    }
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

        if ($dotCount <= 0 || $key[0] === '.' || $key[-1] === '.') {
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
