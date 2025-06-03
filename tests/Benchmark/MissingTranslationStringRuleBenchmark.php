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

namespace jbboehr\PHPStanLostInTranslation\Tests\Benchmark;

use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpBench\Attributes\Assert;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PHPStan\Type\Constant\ConstantStringType;

final class MissingTranslationStringRuleBenchmark
{
    private TranslationLoader $loader;

    private MissingTranslationStringRule $rule;

    public function __construct()
    {
        $this->loader = new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'en',
        );

        $this->rule = new MissingTranslationStringRule($this->loader);

        try {
            mt_srand(1234);

            $this->generateExtraData();
        } finally {
            mt_srand();
        }
    }

    #[Iterations(5)]
    #[Revs(10)]
    #[Assert('mode(variant.time.avg) < 50 milliseconds +/- 10%')]
    public function benchProcessCall(): void
    {
        $this->rule->processCall(new TranslationCall(
            null,
            'functionName',
            __FILE__,
            -1,
            [
                'foo' => [['*', null]],
            ],
            keyType: new ConstantStringType('foo'),
        ));
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

            $this->loader->add('ja', $buf, $buf);
        }
    }
}
