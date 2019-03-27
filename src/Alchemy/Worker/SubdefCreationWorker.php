<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Media\SubdefGenerator;
use Alchemy\Worker\Worker;
use Silex\Application;

class SubdefCreationWorker implements Worker
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
            $recordId  = $payload['recordId'];
            $databoxId = $payload['databoxId'];

            $record = $this->findDataboxById($databoxId)->get_record($recordId);

            /** @var SubdefGenerator $subdefGenerator */
            $subdefGenerator = $this->app['subdef.generator'];

            $subdefGenerator->generateSubdefs($record);
        }
    }
}