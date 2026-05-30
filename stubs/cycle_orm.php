<?php

namespace Cycle\ORM;

interface ORMInterface
{
    public function getHeap(): HeapInterface;
}

interface HeapInterface
{
    public function clean(): void;
}
