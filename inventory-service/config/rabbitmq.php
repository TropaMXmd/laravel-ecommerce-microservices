<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    'exchanges' => [
        'orders' => 'orders',
        'inventory' => 'inventory',
        'notifications' => 'notifications',
    ],

    'dead_letter_exchange' => 'dead_letters',

    'consumers' => [
        'order_placed' => [
            'exchange' => 'orders',
            'routing_key' => 'order.placed',
            'queue' => 'inventory.order.placed',
            'handler' => \App\Consumers\OrderPlacedConsumer::class,
        ],
        'order_confirmed' => [
            'exchange' => 'orders',
            'routing_key' => 'order.confirmed',
            'queue' => 'inventory.order.confirmed',
            'handler' => \App\Consumers\OrderConfirmedConsumer::class,
        ],
        'order_cancelled' => [
            'exchange' => 'orders',
            'routing_key' => 'order.cancelled',
            'queue' => 'inventory.order.cancelled',
            'handler' => \App\Consumers\OrderCancelledConsumer::class,
        ],
    ],
];
