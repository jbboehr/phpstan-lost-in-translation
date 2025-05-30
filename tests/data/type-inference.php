<?php

$key = 'foo';
__($key);

foreach (['foo', 'bar'] as $key) {
    __($key);
}

/**
 * @phpstan-return "two"|"three"
 */
//function getKey() {
//    return random_int(0, 10) > 5 ? 'two' : 'three';
//}
//__(\getKey());

const KEY = 'foo';
__(KEY);

/** @var array{foo: mixed, bar: mixed} $map */
foreach ($map as $key => $value) {
    __($key);
}
