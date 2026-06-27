<?php

declare(strict_types=1);

namespace Folk\Yii3\Jobs;

use Folk\Sdk\Jobs\JobsModeHandler;
use Yiisoft\Queue\Message\Serializer\MessageSerializerInterface;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Queue\Worker\WorkerInterface;

/**
 * Consume side of the native yiisoft/queue bridge.
 *
 * folk-plugin-jobs delivers {queue, payload} to jobs.process; payload carries
 * the serialized message produced by {@see FolkQueueAdapter}. We unserialize it
 * with the same MessageSerializer and run it through Yii's Worker, which
 * resolves the handler by message type and executes the consume middleware
 * pipeline — the native yiisoft/queue consume path.
 */
final class FolkQueueHandler implements JobsModeHandler
{
    public function __construct(
        private readonly WorkerInterface $worker,
        private readonly QueueInterface $queue,
        private readonly MessageSerializerInterface $serializer,
    ) {}

    public function process(mixed $payload): mixed
    {
        $body = $this->unwrapBody($payload);

        $message = $this->serializer->unserialize($body);
        $this->worker->process($message, $this->queue);

        return ['status' => 'ok'];
    }

    private function unwrapBody(mixed $payload): string
    {
        if (\is_array($payload)) {
            return isset($payload['payload']) ? (string) $payload['payload'] : '';
        }

        return (string) $payload;
    }
}
