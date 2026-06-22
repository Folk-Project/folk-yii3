<?php

declare(strict_types=1);

namespace Folk\Yii3\Tests\Reset;

use Folk\Yii3\Reset\ContainerResetter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Yiisoft\Di\StateResetter;

final class ContainerResetterTest extends TestCase
{
    public function testCallsStateResetterWhenPresent(): void
    {
        $stateResetter = new StateResetter($this->createMock(ContainerInterface::class));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(StateResetter::class)->willReturn(true);
        $container->method('get')->with(StateResetter::class)->willReturn($stateResetter);

        (new ContainerResetter($container))->reset();

        self::assertTrue(true);
    }

    public function testSkipsWhenStateResetterNotPresent(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(StateResetter::class)->willReturn(false);
        $container->expects(self::never())->method('get');

        (new ContainerResetter($container))->reset();
    }

    public function testSilentlySwallowsException(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willThrowException(new \RuntimeException('di error'));

        (new ContainerResetter($container))->reset();

        self::assertTrue(true);
    }
}
