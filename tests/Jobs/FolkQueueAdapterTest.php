<?php

declare(strict_types=1);

namespace {
    // Stub the native folk_call() so the adapter can be exercised without the
    // Folk extension loaded. Captures the last call.
    if (!\function_exists('folk_call')) {
        function folk_call(string $method, string $payload): string
        {
            $GLOBALS['__folk_test_calls'][] = ['method' => $method, 'payload' => $payload];

            return '{"status":"ok"}';
        }
    }
}

namespace Folk\Yii3\Tests\Jobs {

    use Folk\Yii3\Jobs\FolkQueueAdapter;
    use PHPUnit\Framework\TestCase;
    use Yiisoft\Queue\Message\DelayEnvelope;
    use Yiisoft\Queue\Message\GenericMessage;
    use Yiisoft\Queue\Message\Serializer\JsonMessageEncoder;
    use Yiisoft\Queue\Message\Serializer\MessageSerializer;

    final class FolkQueueAdapterTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__folk_test_calls'] = [];
        }

        private function serializer(): MessageSerializer
        {
            return new MessageSerializer(new JsonMessageEncoder());
        }

        public function testPushSerializesMessageToChannel(): void
        {
            $serializer = $this->serializer();
            $adapter = new FolkQueueAdapter($serializer, 'redis.emails');

            $message = GenericMessage::fromPayload('app.send-email', ['to' => 'user@example.com']);
            $adapter->push($message);

            self::assertCount(1, $GLOBALS['__folk_test_calls']);
            $call = $GLOBALS['__folk_test_calls'][0];
            self::assertSame('jobs.push', $call['method']);

            $outer = \json_decode($call['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('redis.emails', $outer['queue']);
            self::assertSame(0, $outer['delay']);

            // The opaque payload must round-trip through the same serializer.
            $restored = $serializer->unserialize($outer['payload']);
            self::assertSame('app.send-email', $restored->getType());
            self::assertSame(['to' => 'user@example.com'], $restored->getPayload());
        }

        public function testPushReadsDelayFromDelayEnvelope(): void
        {
            $adapter = new FolkQueueAdapter($this->serializer(), 'default');

            $message = new DelayEnvelope(
                GenericMessage::fromPayload('app.noop', null),
                2.0,
            );
            $adapter->push($message);

            $outer = \json_decode($GLOBALS['__folk_test_calls'][0]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('default', $outer['queue']);
            self::assertSame(2, $outer['delay']);
        }
    }
}
