<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Media\SubdefGenerator;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Silex\Application;

class SubdefCreationWorker implements WorkerInterface
{
    use ApplicationBoxAware;

    private $app;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->messagePublisher = $this->app['alchemy_service.message.publisher'];
    }

    public function process(array $payload)
    {
        if(isset($payload['recordId']) && isset($payload['databoxId'])) {
            $recordId  = $payload['recordId'];
            $databoxId = $payload['databoxId'];

            $record = $this->findDataboxById($databoxId)->get_record($recordId);

            /** @var SubdefGenerator $subdefGenerator */
            $subdefGenerator = $this->app['subdef.generator'];

            if(!$record->isStory()){
                $start = microtime(true);

                $subdefGenerator->generateSubdefs($record);

                $stop = microtime(true);
                $duration = $stop - $start;

                $this->messagePublisher->pushLog(sprintf("subdefCreation done for record_id= %d , duration = %s",
                    $record->getRecordId(),
                    date('H:i:s', mktime(0,0, $duration))
                ));
            }

        }
    }
}
