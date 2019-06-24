<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\EntityManagerAware;
use Alchemy\Phrasea\Application\Helper\BorderManagerAware;
use Alchemy\Phrasea\Application\Helper\DispatcherAware;
use Alchemy\Phrasea\Application\Helper\FilesystemAware;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Border\Attribute\MetaField;
use Alchemy\Phrasea\Border\Attribute\Status;
use Alchemy\Phrasea\Border\File;
use Alchemy\Phrasea\Border\Visa;
use Alchemy\Phrasea\Core\Event\LazaretEvent;
use Alchemy\Phrasea\Core\Event\RecordEdit;
use Alchemy\Phrasea\Core\PhraseaEvents;
use Alchemy\Phrasea\Model\Entities\LazaretFile;
use Alchemy\Phrasea\Model\Entities\LazaretSession;
use GuzzleHttp\Client;

class CreateRecordWorker implements WorkerInterface
{
    use EntityManagerAware;
    use BorderManagerAware;
    use DispatcherAware;
    use FilesystemAware;

    private $app;
    private $logger;

    public function __construct(PhraseaApplication $app)
    {
        $this->app              = $app;
        $this->logger           = $this->app['alchemy_service.logger'];
    }

    public function process(array $payload)
    {
        $uploaderConfig = $this->app['worker_plugin.config']['worker_plugin'];

        $uploaderClient = new Client(['base_uri' => $uploaderConfig['url_uploader_service']]);


        //get asset informations
        $body = $uploaderClient->get('/assets/'.$payload['asset'], [
            'headers' => [
                'Authorization' => 'AssetToken '.$payload['assetToken']
            ]
        ])->getBody()->getContents();

        $body = json_decode($body,true);

        $tempfile = $this->getTemporaryFilesystem()->createTemporaryFile('download_', null, pathinfo($body['originalName'], PATHINFO_EXTENSION));

        //download the asset
        $res = $uploaderClient->get('/assets/'.$payload['asset'].'/download', [
            'headers' => [
                'Authorization' => 'AssetToken '.$payload['assetToken']
            ],
            'save_to' => $tempfile
        ]);

        if($res->getStatusCode() !== 200) {
            $this->logger->error(sprintf('Error %s downloading "%s"', $res->getStatusCode(), $uploaderConfig['url_uploader_service'].'/assets/'.$payload['asset'].'/download'));
        }


        $lazaretSession = new LazaretSession();
        //TODO: get an authenticatedUser
//            $lazaretSession->setUser($this->getAuthenticatedUser());

        $this->getEntityManager()->persist($lazaretSession);


        $renamedFilename = $tempfile;
        $media = $this->app->getMediaFromUri($renamedFilename);

        if(!isset($body['formData']['collection_destination'])){
            return ;
        }

        $base_id = $body['formData']['collection_destination'];
        $collection = \collection::getByBaseId($this->app, $base_id);
        $sbasId = $collection->get_sbas_id();

        $packageFile = new File($this->app, $media, $collection, $body['originalName']);

        // get metadata and status
        $statusbit = null;
        foreach($body['formData'] as $key => $value){
            if(strstr($key, 'metadata')){
                $tMeta = explode('-', $key);

                $metaField = $collection->get_databox()->get_meta_structure()->get_element($tMeta[1]);

                $packageFile->addAttribute(new MetaField($metaField, [$value]));
            }

            if(strstr($key, 'statusbit')){
                $tStatus = explode('-', $key);
                $statusbit[$tStatus[1]] = $value;
            }
        }

        if(!is_null($statusbit)){
            $status = '';
            foreach (range(0, 31) as $i) {
                $status .= isset($statusbit[$i]) ? ($statusbit[$i] ? '1' : '0') : '0';
            }
            $packageFile->addAttribute(new Status($this->app, strrev($status)));
        }

        $reasons = [];
        $elementCreated = null;

        $callback = function ($element, Visa $visa) use (&$reasons, &$elementCreated) {
            foreach ($visa->getResponses() as $response) {
                if (!$response->isOk()) {
                    $reasons[] = $response->getMessage($this->app['translator']);
                }
            }

            $elementCreated = $element;
        };

        $this->getBorderManager()->process( $lazaretSession, $packageFile, $callback);

        if ($elementCreated instanceof \record_adapter) {
            $this->logger->info(sprintf('The record record_id= %d was successfully created', $elementCreated->getRecordId()));

            $this->dispatch(PhraseaEvents::RECORD_UPLOAD, new RecordEdit($elementCreated));

        } else {
            /** @var LazaretFile $elementCreated */
            $this->dispatch(PhraseaEvents::LAZARET_CREATE, new LazaretEvent($elementCreated));

            $this->logger->info('The file was moved to the quarantine');
        }

        // add record in a story if story is defined

        if(is_int($payload['storyId'])){
            $story = new \record_adapter($this->app, $sbasId, $payload['storyId']);

            //TODO:
//            if (!$this->getAclForUser()->has_right_on_base($Story->getBaseId(), \ACL::CANMODIFRECORD)) {
//                throw new AccessDeniedHttpException('You can not add document to this Story');
//            }

            if(!$story->hasChild($elementCreated)){
                $story->appendChild($elementCreated);

                $this->logger->info(sprintf('The record record_id= %d was successfully added in the story record_id= %d', $elementCreated->getRecordId(), $story->getRecordId()));
                $this->dispatch(PhraseaEvents::RECORD_EDIT, new RecordEdit($story));
            }

        }

    }
}
