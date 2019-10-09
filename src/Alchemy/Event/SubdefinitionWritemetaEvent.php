<?php

namespace  Alchemy\WorkerPlugin\Event;

use Alchemy\Phrasea\Core\Event\Record\RecordEvent;
use Alchemy\Phrasea\Model\RecordInterface;

class SubdefinitionWritemetaEvent extends RecordEvent
{
    const CREATE = 'create';
    const FAILED = 'failed';

    private $status;
    private $subdefName;

    public function __construct(RecordInterface $record, $subdefName, $status = self::CREATE)
    {
        parent::__construct($record);

        $this->subdefName = $subdefName;
        $this->status = $status;
    }

    public function getSubdefName()
    {
        return $this->subdefName;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
