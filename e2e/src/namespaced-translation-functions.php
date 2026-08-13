<?php

namespace App\TranslationFallback {

    __('namespaced double underscore');
    trans('namespaced trans');
    trans_choice('namespaced one|namespaced many', 1);
    TrAnS('namespaced mixed-case trans');
}

namespace App\TranslationOverrides {

    function __(string $key): string
    {
        return $key;
    }

    echo __('local override');
}
