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
            // HTTP handler
            $loop->registerHttpHandler(
                new Handler\YiiHttpHandler($container),
            );

            // Jobs handler
            $loop->registerJobsHandler(
                new Jobs\YiiJobHandler($container),
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

            if ($container->has(\Cycle\ORM\ORMInterface::class)) {
                $loop->registerResetter(new Reset\CycleResetter($container));
            }
        };
    }
}
