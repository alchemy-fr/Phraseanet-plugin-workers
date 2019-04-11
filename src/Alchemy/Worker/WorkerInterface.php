<?php

namespace Alchemy\WorkerPlugin\Worker;

interface WorkerInterface
{
    /**
     * @param  array $payload
     * @return mixed
     */
    public function process(array $payload);
}