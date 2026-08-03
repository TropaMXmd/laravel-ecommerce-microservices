<?php

return [

    'admin' => '*',

    'customer' => [

        'orders.view',
        'orders.create',

    ],

    'warehouse' => [

        'inventory.view',
        'inventory.reserve',
        'inventory.release',

    ],

];