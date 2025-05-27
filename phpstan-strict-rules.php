<?php

if (version_compare(\Composer\InstalledVersions::getVersion('phpstan/phpstan-strict-rules'), '2.0', '>=')) {
    return [
        'parameters' => [
            'strictRules' => [
                'disallowedShortTernary' => false,
                'dynamicCallOnStaticMethod' => false,
            ],
        ],
    ];
} else {
    return [
        'parameters' => [
            'strictRules' => [
                'disallowedConstructs' => false,
                'strictCalls' => false,
            ],
        ],
    ];
}

