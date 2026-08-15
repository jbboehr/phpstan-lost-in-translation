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

namespace jbboehr\PHPStanLostInTranslation\Tests\Benchmark;

use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Type\Constant\ConstantStringType;

final class MissingTranslationStringRuleBenchmark
{
    /** @var list<non-empty-string> */
    private array $extraData = [];

    private TranslationLoader $loader;

    private MissingTranslationStringRule $rule;

    private TranslationCall $call;

    public function __construct()
    {
        $this->call = new TranslationCall(
            null,
            'functionName',
            __FILE__,
            -1,
            [
                'foo' => [['*', null]],
            ],
            keyType: new ConstantStringType('foo'),
        );

        try {
            mt_srand(1234);

            $this->generateExtraData();
        } finally {
            mt_srand();
        }

        $this->setUpColdMissingKey();
    }

    public function setUpColdMissingKey(): void
    {
        $this->loader = new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'en',
        );

        foreach ($this->extraData as $value) {
            $this->loader->add('ja', $value, $value);
        }

        $this->rule = new MissingTranslationStringRule($this->loader);
    }

    #[Iterations(5)]
    #[Revs(1)]
    #[BeforeMethods('setUpColdMissingKey')]
    public function benchColdMissingKey(): void
    {
        $this->assertSingleError($this->rule->processCall($this->call));
    }

    public function setUpWarmMissingKey(): void
    {
        $this->setUpColdMissingKey();
        $this->assertSingleError($this->rule->processCall($this->call));
    }

    #[Iterations(5)]
    #[Revs(100)]
    #[BeforeMethods('setUpWarmMissingKey')]
    public function benchWarmMissingKey(): void
    {
        $this->assertSingleError($this->rule->processCall($this->call));
    }

    private function generateExtraData(): void
    {
        $chars = '';

        for ($i = 0; $i <= 255; $i++) {
            if (ctype_print(chr($i))) {
                $chars .= chr($i);
            }
        }

        for ($i = 0; $i < 10000; $i++) {
            $buf = '';

            for ($j = 0, $l = mt_rand(5, 100); $j < $l; $j++) {
                $buf .= $chars[mt_rand(0, strlen($chars) - 1)];
            }

            if ('' !== $buf) {
                $this->extraData[] = $buf;
            }
        }
    }

    /** @param list<IdentifierRuleError> $errors */
    private function assertSingleError(array $errors): void
    {
        if (1 !== count($errors)) {
            throw new \RuntimeException(sprintf('Expected one diagnostic, got %d', count($errors)));
        }
    }
}
