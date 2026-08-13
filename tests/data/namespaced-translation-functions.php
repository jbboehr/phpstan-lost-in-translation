<?php

namespace App\Probe;

__('namespaced double underscore');
trans('namespaced trans');
trans_choice('namespaced trans choice', 1);
TrAnS('namespaced mixed-case trans');

namespace Vendor;

function translate(string $key): string
{
    return $key;
}

namespace App\ImportedAlias;

use function Vendor\translate as trans;

trans('imported function alias');
