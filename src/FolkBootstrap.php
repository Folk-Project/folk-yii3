<?php

declare(strict_types=1);

namespace Folk\Yii3;

use Folk\Sdk\Grpc\GrpcRouter;
use Folk\Sdk\Worker\HandlerLoop;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class FolkBootstrap
{
    public static function register(ContainerInterface $container): void
    {
        if (!\function_exists('folk_worker_run')) {
            return;
        }

        $GLOBALS['folk_worker_boot_hook'] = static function (HandlerLoop $loop) use ($container): void {
            // Stamp request_id into the application log context for correlation
            // with Folk's Rust-side access log. Reads Folk::requestId() at log
            // time, so nothing to reset between requests on a recycled worker.
            try {
                if ($container->has(\Psr\Log\LoggerInterface::class)) {
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);
                    if ($logger instanceof \Monolog\Logger) {
                        // Monolog path (e.g. apps using monolog/monolog directly)
                        $logger->pushProcessor(new Log\FolkRequestIdProcessor());
                    } elseif (
                        \class_exists(\Yiisoft\Log\Logger::class)
                        && $logger instanceof \Yiisoft\Log\Logger
                        && \interface_exists(\Yiisoft\Log\ContextProvider\ContextProviderInterface::class)
                    ) {
                        // yiisoft/log path — wrap the existing context provider so
                        // request_id is merged into every log record's context at
                        // write time. Uses reflection because contextProvider is
                        // private (PHP 8.1+ reflection is always accessible).
                        $prop = new \ReflectionProperty($logger, 'contextProvider');
                        /** @var \Yiisoft\Log\ContextProvider\ContextProviderInterface $existing */
                        $existing = $prop->getValue($logger);
                        $prop->setValue(
                            $logger,
                            new class ($existing) implements \Yiisoft\Log\ContextProvider\ContextProviderInterface {
                                public function __construct(
                                    private readonly \Yiisoft\Log\ContextProvider\ContextProviderInterface $inner,
                                ) {}

                                /** @return array<string, mixed> */
                                public function getContext(): array
                                {
                                    $id = \Folk\Sdk\Folk::requestId();
                                    $ctx = $this->inner->getContext();
                                    return $id !== ''
                                        ? \array_merge($ctx, ['request_id' => $id])
                                        : $ctx;
                                }
                            },
                        );
                    }
                }
            } catch (\Throwable) {
                // Logger integration is optional — never fail the worker bootstrap
            }

            // HTTP handler
            $loop->registerHttpHandler(
                new Handler\YiiHttpHandler($container),
            );

            // Jobs handler — prefer the native yiisoft/queue bridge (run through
            // the Yii Worker) when the queue package is installed and wired; fall
            // back to the bespoke container-resolved handler otherwise.
            $nativeJobs = null;
            try {
                if (
                    \interface_exists(\Yiisoft\Queue\Worker\WorkerInterface::class)
                    && $container->has(\Yiisoft\Queue\Worker\WorkerInterface::class)
                    && $container->has(\Yiisoft\Queue\QueueInterface::class)
                    && $container->has(\Yiisoft\Queue\Message\Serializer\MessageSerializerInterface::class)
                ) {
                    $nativeJobs = new Jobs\FolkQueueHandler(
                        $container->get(\Yiisoft\Queue\Worker\WorkerInterface::class),
                        $container->get(\Yiisoft\Queue\QueueInterface::class),
                        $container->get(\Yiisoft\Queue\Message\Serializer\MessageSerializerInterface::class),
                    );
                }
            } catch (\Throwable) {
                $nativeJobs = null;
            }

            $loop->registerJobsHandler(
                $nativeJobs ?? new Jobs\YiiJobHandler($container),
            );

            // gRPC handler (if configured)
            try {
                if ($container->has('folk.grpc.services')) {
                    /** @var array<string, class-string> $grpcServices */
                    $grpcServices = $container->get('folk.grpc.services');
                    if ($grpcServices !== []) {
                        $router = new GrpcRouter();
                        foreach ($grpcServices as $name => $class) {
                            $router->register($name, $container->get($class));
                        }
                        $loop->registerGrpcHandler($router);
                    }
                }
            } catch (\Throwable) {
            }

            // Resetters
            $loop->registerResetter(new Reset\ContainerResetter($container));
            $loop->registerResetter(new \Folk\Sdk\Reset\TempUploadResetter());

            if ($container->has(\Cycle\ORM\ORMInterface::class)) {
                $loop->registerResetter(new Reset\CycleResetter($container));
            }
        };
    }
}
