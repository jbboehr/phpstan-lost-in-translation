<?php

use Illuminate\Contracts\View\Factory as ViewFactory;

/** @var \PHPStan\DependencyInjection\MemoizingContainer $container */

if (version_compare(\Composer\InstalledVersions::getVersion('tomasvotruba/bladestan'), '0.7', '>=')) {
    require __DIR__ . '/../vendor/tomasvotruba/bladestan/bootstrap.php';
    /** @var \Illuminate\Contracts\Foundation\Application $app */
    $app->langPath(__DIR__ . '/lang');
    $app->resourcePath(__DIR__ . '/resources');
    $app->make(ViewFactory::class)
        ->getFinder()
        ->addLocation(__DIR__ . '/resources/views');
} else {
    $templateFilePathResolver = $container->getByType(\TomasVotruba\Bladestan\NodeAnalyzer\TemplateFilePathResolver::class);
    $reflection = new ReflectionProperty(
        $templateFilePathResolver,
        'fileViewFinder',
    );
    $fileViewFinder = $reflection->getValue($templateFilePathResolver);
    $reflection = new ReflectionProperty(
        $fileViewFinder,
        'paths'
    );
    $reflection->setAccessible(true);
    $reflection->setValue($fileViewFinder, [
        __DIR__ . '/resources/views',
    ]);
}
