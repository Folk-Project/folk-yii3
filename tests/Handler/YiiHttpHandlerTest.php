<?php

declare(strict_types=1);

namespace Folk\Yii3\Tests\Handler;

use Folk\Sdk\Http\HttpRequest;
use Folk\Yii3\Handler\YiiHttpHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Yii\Http\Application;

final class YiiHttpHandlerTest extends TestCase
{
    public function testHandlesGetRequest(): void
    {
        $handler = $this->makeHandler(new Response(200, [], 'ok'));

        $response = $handler->handle(new HttpRequest('GET', '/', [], ''));

        self::assertSame(200, $response->status);
        self::assertSame('ok', $response->body);
    }

    public function testHandlesPostRequestWithBody(): void
    {
        $handler = $this->makeHandler(new Response(201, [], 'created'));

        $response = $handler->handle(new HttpRequest('POST', '/items', ['content-type' => 'application/json'], '{"x":1}'));

        self::assertSame(201, $response->status);
        self::assertSame('created', $response->body);
    }

    public function testReturns404Response(): void
    {
        $handler = $this->makeHandler(new Response(404));

        $response = $handler->handle(new HttpRequest('GET', '/missing', [], ''));

        self::assertSame(404, $response->status);
    }

    public function testForwardsResponseHeaders(): void
    {
        $handler = $this->makeHandler(new Response(200, ['X-Folk' => 'test'], 'ok'));

        $response = $handler->handle(new HttpRequest('GET', '/', [], ''));

        self::assertArrayHasKey('X-Folk', $response->headers);
        self::assertSame('test', $response->headers['X-Folk']);
    }

    private function makeHandler(Response $psrResponse): YiiHttpHandler
    {
        $factory = new Psr17Factory();

        $app = $this->createMock(Application::class);
        $app->method('handle')->willReturn($psrResponse);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [ServerRequestFactoryInterface::class, $factory],
            [StreamFactoryInterface::class, $factory],
            [Application::class, $app],
        ]);

        return new YiiHttpHandler($container);
    }
}
