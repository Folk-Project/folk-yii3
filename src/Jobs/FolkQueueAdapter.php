<?php

declare(strict_types=1);

namespace Folk\Yii3\Jobs;

use Folk\Sdk\Uuid;
use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Message\DelayEnvelope;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\MessageStatus;
use Yiisoft\Queue\Message\Serializer\MessageSerializerInterface;

/**
 * Native yiisoft/queue adapter backed by Folk's jobs plugin.
 *
 * Implements `Yiisoft\Queue\Adapter\AdapterInterface`, so application code uses
 * the idiomatic `$queue->push(new Message(...))`. The message is serialized
 * with the queue's MessageSerializer and handed to folk-plugin-jobs via
 * folk_call(); Folk drives consumption (worker → jobs.process, routed back
 * through the Yii worker by {@see FolkQueueHandler}), so the pull-side methods
 * (runExisting/subscribe) are inert.
 *
 * The channel name doubles as the Folk queue address ([<connection>.]<queue>).
 */
final class FolkQueueAdapter implements AdapterInterface
{
    public function __construct(
        private readonly MessageSerializerInterface $serializer,
        private readonly string $channel = 'default',
    ) {}

    public function runExisting(callable $handlerCallback): void
    {
        // Folk drives consumption (worker → jobs.process); nothing to pull.
    }

    public function status(string|int $id): MessageStatus
    {
        // Folk's simple-tier drivers do not expose per-message status tracking.
        return MessageStatus::NOT_FOUND;
    }

    public function push(MessageInterface $message): MessageInterface
    {
        $delaySeconds = (int) \ceil(DelayEnvelope::fromMessage($message)->getDelaySeconds());

        // Stamp a time-ordered id (consistent with Folk's request_id), so the
        // message carries an identifier across the Folk round-trip.
        $message = new IdEnvelope($message, Uuid::v7());

        \folk_call('jobs.push', \json_encode([
            'queue' => $this->channel,
            'payload' => $this->serializer->serialize($message),
            'delay' => $delaySeconds,
        ], JSON_THROW_ON_ERROR));

        return $message;
    }

    public function subscribe(callable $handlerCallback): void
    {
        // Folk drives consumption (worker → jobs.process); nothing to subscribe to.
    }
}
