<?php

declare(strict_types=1);

namespace Folk\Yii3\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Psr\Container\ContainerInterface;

/**
 * Resets Yii3 container state between requests.
 *
 * Uses yiisoft/di StateResetter which calls reset()
 * on all services registered as resettable.
 */
final class ContainerResetter implements ResettableInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function reset(): void
    {
        try {
            if ($this->container->has(\Yiisoft\Di\StateResetter::class)) {
                $this->container->get(\Yiisoft\Di\StateResetter::class)->reset();
            }
        } catch (\Throwable) {
        }
    }
}
