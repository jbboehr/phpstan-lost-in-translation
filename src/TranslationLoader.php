<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use Illuminate\Support\NamespacedItemResolver;
use Illuminate\Translation\Translator;
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

    public function __construct(
        ?string $langPath = null,
    ) {
        if ($langPath === null) {
//            if (function_exists('lang_path')) {
//                $langPath = lang_path();
//            } else {
                $langPath = 'lang';
//            }
        }

        $this->langPath = $langPath;
        $this->namespacedItemResolver = new NamespacedItemResolver();

        $this->scan();
    }

    /**
     * @return list<string>
     */
    public function getFoundLocales(): array
    {
        return $this->foundLocales;
    }

    public function has(string $locale, string $key): bool
    {
        return $this->get($locale, $key) !== null;
    }

    public function hasLocale(string $locale): bool
    {
        return isset($this->data[$locale]);
    }

    public function get(string $locale, string $key): ?string
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        return $this->data[$locale][$namespace][$group][$item] ?? null;
    }

    public function markUsed(string $locale, string $key): void
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        if ($locale === '*') {
            $locales = $this->getFoundLocales();
        } else {
            $locales = [$locale];
        }

        foreach ($locales as $k_locale) {
            $this->used[$k_locale][$namespace][$group][$item] = true;
        }
    }

    /**
     * @return list<array{string, string}>
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

                            $possiblyUnused[] = [$locale, $buf];
                        }
                    }
                }
            }
        }

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

            $raw = match ($file->getExtension()) {
                'php' => $this->loadPhp($file),
                'json' => $this->loadJson($file),
                default => null,
            };

            if (!is_array($raw)) {
                $this->warnings[] = sprintf("Invalid data type %s in file %s", gettype($raw), $file->getRelativePathname());
                continue;
            }

            foreach ($raw as $k => $v) {
                if (!is_string($k)) {
                    $this->warnings[] = sprintf("Invalid key \"%s\" in file %s", print_r($k, true), $file->getRelativePathname());
                    continue;
                }
                if (!is_string($v)) {
                    $this->warnings[] = sprintf(
                        "Invalid data type %s for key \"%s\"  in file %s",
                        gettype($v),
                        $k,
                        $file->getRelativePathname()
                    );

                    continue;
                }

                $this->data[$locale][$namespace][$group][$k] = $v;
            }
        }

        $this->foundLocales = array_keys($foundLocales);
    }

    private function loadPhp(SplFileInfo $file): mixed
    {
        return (static function (string $__): mixed {
            return require $__;
        })($file->getPathname());
    }

    private function loadJson(SplFileInfo $file): mixed
    {
        return json_decode($file->getContents(), true, JSON_THROW_ON_ERROR);
    }
}
