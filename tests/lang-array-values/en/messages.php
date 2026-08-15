<?php

return [
    'options.one' => 'Literal :literal',
    'options' => [
        'one' => 'Nested :nested',
        'two' => 'Two :name',
        'nested' => [
            'label' => 'Label :label',
        ],
    ],
];
