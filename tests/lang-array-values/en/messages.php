<?php

return [
    'options.one' => 'Literal :literal',
    'options.nested' => 'Literal nested :literal_nested',
    'options' => [
        'one' => 'Nested :nested',
        'two' => 'Two :name',
        'nested' => [
            'label' => 'Label :label',
        ],
    ],
    'optionsExtra' => 'Prefix sibling',
];
