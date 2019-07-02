<?php

namespace Alchemy\WorkerPlugin\Worker;

use Silex\Application;

class WriteLogsWorker implements WorkerInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function process(array $payload)
    {
        $message = $payload['message'];
        $this->app['alchemy_service.logger']->info($message);
    }
}
