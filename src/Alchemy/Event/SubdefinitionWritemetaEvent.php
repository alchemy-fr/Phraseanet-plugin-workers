<?php

namespace  Alchemy\WorkerPlugin\Event;

use Alchemy\Phrasea\Core\Event\Record\RecordEvent;
use Alchemy\Phrasea\Model\RecordInterface;

class SubdefinitionWritemetaEvent extends RecordEvent
{
    private $subdefName;

    public function __construct(RecordInterface $record, $subdefName)
    {
        parent::__construct($record);

        $this->subdefName = $subdefName;
    }

    public function getSubdefName()
    {
        return $this->subdefName;
    }
}
