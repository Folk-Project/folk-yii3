<?php

declare(strict_types=1);

namespace Folk\Yii3\Handler;

use Folk\Sdk\Http\PsrHttpHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Yii\Http\Application;

final class YiiHttpHandler extends PsrHttpHandler
{
    private readonly Application $app;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct(
            $container->get(ServerRequestFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        $this->app = $container->get(Application::class);
    }

    protected function handlePsr(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }
}
