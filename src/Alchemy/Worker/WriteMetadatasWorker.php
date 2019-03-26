<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Worker\Worker;
use Silex\Application;

class WriteMetadatasWorker implements Worker
{
    use ApplicationBoxAware;

    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function process(array $payload)
    {
        if(isset($payload['recordId']) && isset($payload['databoxId'])) {
           // TODO Write metadatas worker here
        }
    }
}
