<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
 *
 * SPDX-License-Identifier: AGPL-3.0-only WITH romic-exception
 */
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->name('*.php')
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath([
        'CallRule/data',
        'CallRule/lang-invalid-character-encoding',
        'Rule/data',
        'Rule/lang-warn',
        'data',
        'fixtures',
        'lang',
        'lang-empty-arrays',
        'lang-locale-collision',
        'lang-scanning',
        'lang-script-locales',
        'resources',
    ])
    ->append([
        __DIR__ . '/phpstan-strict-rules.php',
        __DIR__ . '/tools/bookstack/assert-output.php',
        __DIR__ . '/tools/check-package.php',
        __DIR__ . '/tools/docs',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        'array_syntax' => ['syntax' => 'short'],
        'encoding' => true,
        'full_opening_tag' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],
    ])
    ->setFinder($finder);
