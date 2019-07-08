<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Metadata\TagFactory;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Monolog\Logger;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\Tag;
use PHPExiftool\Driver\Value\Mono;
use PHPExiftool\Driver\Value\Multi;
use PHPExiftool\Exception\ExceptionInterface as PHPExiftoolException;
use PHPExiftool\Exception\TagUnknown;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;

class WriteMetadatasWorker implements WorkerInterface
{
    use ApplicationBoxAware;

    /** @var Logger  */
    private $logger;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    /** @var  Writer $writer */
    private $writer;

    public function __construct(Writer $writer, LoggerInterface $logger, MessagePublisher $messagePublisher)
    {
        $this->writer           = $writer;
        $this->logger           = $logger;
        $this->messagePublisher = $messagePublisher;
    }

    public function process(array $payload)
    {
        if (isset($payload['recordId']) && isset($payload['databoxId'])) {
            $recordId  = $payload['recordId'];
            $databoxId = $payload['databoxId'];

            $MWG      = isset($payload['MWG']) ? $payload['MWG'] : false;
            $clearDoc = isset($payload['clearDoc']) ? $payload['clearDoc'] : false;

            $databox = $this->findDataboxById($databoxId);
            $record  = $databox->get_record($recordId);
            $type    = $record->getType();

            // retrieve subdefs existed physically and subdefmetadatarequired
            $subdefs = [];
            foreach ($record->get_subdefs() as $name => $subdef) {
                if ($subdef->is_physically_present() && ($name == 'document' || $this->isSubdefMetadataUpdateRequired($databox, $type, $name))) {
                    $subdefs[$name] = $subdef->getRealPath();
                }
            }

            $metadata = new MetadataBag();

            // add Uuid in metadatabag
            if ($record->getUuid()) {
                $metadata->add(
                    new Metadata(
                        new Tag\XMPExif\ImageUniqueID(),
                        new Mono($record->getUuid())
                    )
                );
                $metadata->add(
                    new Metadata(
                        new Tag\ExifIFD\ImageUniqueID(),
                        new Mono($record->getUuid())
                    )
                );
                $metadata->add(
                    new Metadata(
                        new Tag\IPTC\UniqueDocumentID(),
                        new Mono($record->getUuid())
                    )
                );
            }

            // read document fields and add to metadatabag
            $caption = $record->get_caption();
            foreach ($databox->get_meta_structure() as $fieldStructure) {

                $tagName = $fieldStructure->get_tag()->getTagname();
                $fieldName = $fieldStructure->get_name();

                // skip fields with no src
                if ($tagName == '' || $tagName == 'Phraseanet:no-source') {
                    continue;
                }

                // check exiftool known tags to skip Phraseanet:tf-*
                try {
                    TagFactory::getFromRDFTagname($tagName);
                } catch (TagUnknown $e) {
                    continue;
                }

                try {
                    $field = $caption->get_field($fieldName);
                    $fieldValues = $field->get_values();

                    if ($fieldStructure->is_multi()) {
                        $values = array();
                        foreach ($fieldValues as $value) {
                            $values[] = $this->removeNulChar($value->getValue());
                        }

                        $value = new Multi($values);
                    } else {
                        $fieldValue = array_pop($fieldValues);
                        $value = $this->removeNulChar($fieldValue->getValue());

                        $value = new Mono($value);
                    }
                } catch(\Exception $e) {
                    // the field is not set in the record, erase it
                    if ($fieldStructure->is_multi()) {
                        $value = new Multi(array(''));
                    }
                    else {
                        $value = new Mono('');
                    }
                }

                $metadata->add(
                    new Metadata($fieldStructure->get_tag(), $value)
                );
            }

            $this->writer->reset();

            if ($MWG) {
                $this->writer->setModule(Writer::MODULE_MWG, true);
            }

            foreach ($subdefs as $name => $file) {
                $this->writer->erase($name != 'document' || $clearDoc, true);
                try {
                    $this->writer->write($file, $metadata);

                    $this->messagePublisher->pushLog(sprintf('meta written for sbasid=%1$d - recordid=%2$d (%3$s)', $databox->get_sbas_id(), $recordId, $name));
                } catch (PHPExiftoolException $e) {
                    $this->logger->error(sprintf('meta NOT written for sbasid=%1$d - recordid=%2$d (%3$s) because "%s"', $databox->get_sbas_id(), $recordId, $name, $e->getMessage()));
                }
            }
        }

    }

    /**
     * @param \databox $databox
     * @param string $subdefType
     * @param string $subdefName
     * @return bool
     */
    private function isSubdefMetadataUpdateRequired(\databox $databox, $subdefType, $subdefName)
    {
        if ($databox->get_subdef_structure()->hasSubdef($subdefType, $subdefName)) {
            return $databox->get_subdef_structure()->get_subdef($subdefType, $subdefName)->isMetadataUpdateRequired();
        }

        return false;
    }

    private function removeNulChar($value)
    {
        return str_replace("\0", "", $value);
    }
}
