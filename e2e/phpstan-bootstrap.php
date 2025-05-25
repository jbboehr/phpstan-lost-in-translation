<?php

use Illuminate\Contracts\View\Factory as ViewFactory;

/** @var \PHPStan\DependencyInjection\MemoizingContainer $container */

$bladeStanVersion = \Composer\InstalledVersions::getVersion('tomasvotruba/bladestan');
if (null !== $bladeStanVersion) {
    if (version_compare($bladeStanVersion, '0.7', '>=')) {
        require __DIR__ . '/../vendor/tomasvotruba/bladestan/bootstrap.php';
        /** @var \Illuminate\Contracts\Foundation\Application $app */
        $app->langPath(__DIR__ . '/lang');
        $app->resourcePath(__DIR__ . '/resources');
        $viewFactory = $app->make(ViewFactory::class);
        /** @var \Illuminate\View\Factory $viewFactory */
        $viewFactory
            ->getFinder()
            ->addLocation(__DIR__ . '/resources/views');
    } else {
        /** @phpstan-ignore-next-line phpstanApi.method */
        $templateFilePathResolver = $container->getByType(\TomasVotruba\Bladestan\NodeAnalyzer\TemplateFilePathResolver::class);
        $reflection = new ReflectionProperty(
            $templateFilePathResolver,
            'fileViewFinder',
        );
        $fileViewFinder = $reflection->getValue($templateFilePathResolver);
        /** @var \Illuminate\View\FileViewFinder $fileViewFinder */
        $reflection = new ReflectionProperty(
            $fileViewFinder,
            'paths'
        );
        $reflection->setAccessible(true);
        $reflection->setValue($fileViewFinder, [
            __DIR__ . '/resources/views',
        ]);
    }
}
