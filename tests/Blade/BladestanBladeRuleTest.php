<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Blade;

use Bladestan\Rules\BladeRule;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Composer;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<BladeRule>
 */
class BladestanBladeRuleTest extends RuleTestCase
{
    public function setUp(): void
    {
        if (!\Composer\InstalledVersions::isInstalled('tomasvotruba/bladestan')) {
            self::markTestSkipped('This test requires Bladestan');
        }

        if (version_compare(\Composer\InstalledVersions::getVersion('tomasvotruba/bladestan'), '0.7', '<')) {
            self::markTestSkipped('This test requires Bladestan >=0.7');
        }

        parent::setUp();
    }

    /**
     * @see https://github.com/laravel/framework/issues/49502#issuecomment-2222592953
     */
    public function tearDown(): void
    {
        parent::tearDown();

        if (class_exists(HandleExceptions::class, false) && method_exists(HandleExceptions::class, 'flushState')) {
            HandleExceptions::flushState();
        }
    }

    protected function getRule(): Rule
    {
        return $this->getContainer()->getByType(BladeRule::class);
    }

    public function testMethods(): void
    {
        // :skull:
        $this->getContainer()->getByType(BladeRule::class);

        resolve(ViewFactory::class)
            ->getFinder()
            ->addLocation(__DIR__ . '/resources/views');

        $this->analyse([
            __DIR__ . '/data/sample.php',
        ], [
            [
                'Missing translation string "blade at directive" for locales: zh, en, ja',
                3,
            ],
            [
                'Missing translation string "blade double underscore" for locales: zh, en, ja',
                3,
            ],
            [
                'Missing translation string "only in ja" for locales: zh, en',
                3,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
         return array_merge(parent::getAdditionalConfigFiles(), [
             __DIR__ . '/config.neon',
         ]);
    }
}
