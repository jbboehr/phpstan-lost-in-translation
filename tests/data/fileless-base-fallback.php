<?php

__('Hello, :name!', ['name' => 'World']);
trans_choice('{0} No items|{1} One item|[2,*] :count items', 2, ['count' => 2]);
