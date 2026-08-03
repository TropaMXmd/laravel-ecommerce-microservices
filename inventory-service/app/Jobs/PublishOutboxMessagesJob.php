<?php

namespace App\Jobs;

use App\Models\InventoryOutbox;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Deliberately NOT ShouldQueue — this project has no queue worker running.
 * $schedule->job() runs non-queued jobs synchronously inline, which is what
 * we want here: a lightweight job invoked directly by the scheduler tick,
 * not dispatched to a queue that nothing is consuming.
 */
class PublishOutboxMessagesJob
{
    use Dispatchable;

    private const EXCHANGE = 'inventory';
    private const BATCH_SIZE = 50;


    public function handle(): void
    {
        $rows = InventoryOutbox::where('published', false)
            ->where('attempts', '<', 5)
            ->orderBy('created_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
        );
        $channel = $connection->channel();
        $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

        foreach ($rows as $row) {
            try {
                $message = new AMQPMessage(json_encode($row->payload), [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]);

                $channel->basic_publish($message, self::EXCHANGE, $row->routing_key);

                $row->update(['published' => true, 'published_at' => now()]);
            } catch (\Throwable $e) {
                $row->increment('attempts');
                Log::error("PublishOutboxMessagesJob: failed to publish outbox row {$row->id}: {$e->getMessage()}");
            }
        }

        $channel->close();
        $connection->close();
    }
}

// Register in bootstrap/app.php withSchedule():
// Schedule::job(new PublishOutboxMessagesJob)->everyFiveSeconds();
