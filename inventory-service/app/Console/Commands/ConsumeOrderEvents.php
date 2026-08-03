<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * php artisan inventory:consume
 * Long-running worker that binds to the `orders` exchange and dispatches
 * each routing key to its consumer class. Run one process per container
 * (supervisor-managed) — not via the HTTP-serving nginx/php-fpm process.
 */
class ConsumeOrderEvents extends Command
{
    protected $signature = 'inventory:consume';
    protected $description = 'Consume order.* events from RabbitMQ and react to them (saga participant)';

    public function handle(): int
    {
        $config = config('rabbitmq');

        $connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
        );
        $channel = $connection->channel();

        $channel->exchange_declare($config['dead_letter_exchange'], 'direct', false, true, false);

        // Declare every exchange the whole system uses, not just the one this
        // consumer binds queues to. exchange_declare() is idempotent — safe to
        // call repeatedly, and safe even if another service also declares the
        // same exchange concurrently. This makes inventory-consumer act as a
        // reliable bootstrapper of the RabbitMQ topology on every boot, so
        // nothing downstream (e.g. a manual Postman publish, or order-service
        // once it exists) can fail with "exchange not found" just because of
        // startup ordering.
        foreach ($config['exchanges'] as $exchange) {
            $channel->exchange_declare($exchange, 'topic', false, true, false);
        }

        foreach ($config['consumers'] as $consumer) {
            $queue = $consumer['queue'];

            $channel->queue_declare($queue, false, true, false, false, false, new \PhpAmqpLib\Wire\AMQPTable([
                'x-dead-letter-exchange' => $config['dead_letter_exchange'],
                'x-message-ttl' => 86400000, // 24h
            ]));
            $channel->queue_bind($queue, $config['exchanges']['orders'], $consumer['routing_key']);

            $handler = app($consumer['handler']);

            $channel->basic_consume($queue, '', false, false, false, false, function ($msg) use ($handler) {
                $handler->handle($msg);
            });

            $this->info("Bound {$queue} to routing key {$consumer['routing_key']}");
        }

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return self::SUCCESS;
    }
}
