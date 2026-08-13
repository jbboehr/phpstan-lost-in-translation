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

/** @var list<int> $items */
trans_choice('{0} There are none|[1,*] There are :count', $items, [], 'en');

/** @var \Countable $countable */
trans_choice('{0} There are none|[1,*] There are :count', $countable, [], 'en');

/** @var \Countable&\ArrayAccess $countableArrayAccess */
trans_choice('{0} There are none|[1,*] There are :count', $countableArrayAccess, [], 'en');

/** @var list<int>|\Countable|non-negative-int $countableOrNumber */
trans_choice('{0} There are none|[1,*] There are :count', $countableOrNumber, [], 'en');

/** A counted collection still needs an explicit zero case. */
/** @var list<int> $itemsWithoutZero */
trans_choice('{1} There is one|[2,*] There are :count', $itemsWithoutZero, [], 'en');

trans_choice('{2} There are two', [1, 2], [], 'en');
trans_choice('{0} There are none', [], [], 'en');

/** @var non-empty-list<int> $nonEmptyItems */
trans_choice('[1,*] There are :count', $nonEmptyItems, [], 'en');

/** A fixed-size collection still reports conditions that omit its exact size. */
trans_choice('{1} There is one', [1, 2], [], 'en');

/** A general Countable may be empty. */
/** @var \Countable $countableWithoutZero */
trans_choice('[1,*] There are :count', $countableWithoutZero, [], 'en');
