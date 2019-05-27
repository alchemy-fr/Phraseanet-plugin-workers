<?php

namespace Alchemy\WorkerPlugin\Worker;

use Silex\Application;

class AssetsWorker implements WorkerInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function process(array $payload)
    {
        //TODO :  treat message from Assets_injest  and make a Mock test
    }
}
