<?php

/** @var non-negative-int $count */
trans_choice('{0} There are none|{1} There is one|[2,*] There are :count', $count, [], 'en');

/** Explicit conditions omit zero from this non-negative domain. */
trans_choice('{1} There is one|[2,*] There are :count', $count, [], 'en');

/** A malformed condition must not cause a second, unreliable coverage diagnostic. */
trans_choice('{0} Nie sú žiadne|{1} Je jedna|[2,3,4] Sú :count|[5,*] Je ich :count', $count, [], 'sk');

/** Suggestions retain the delimiters used by the source condition. */
trans_choice('{2,3,4} There are :count', 3, [], 'en');

/** An unknown domain cannot produce an actionable completeness diagnostic. */
/** @var mixed $unknown */
trans_choice('{0} There are none|{1} There is one|[2,*] There are :count', $unknown, [], 'en');
