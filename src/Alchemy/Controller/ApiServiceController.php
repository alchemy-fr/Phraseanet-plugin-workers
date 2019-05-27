<?php

namespace Alchemy\WorkerPlugin\Controller;

use Alchemy\Phrasea\Application\Helper\DispatcherAware;
use Alchemy\Phrasea\Application\Helper\JsonBodyAware;
use Alchemy\Phrasea\Controller\Api\Result;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\WorkerPlugin\Event\AssetsCreateEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Symfony\Component\HttpFoundation\Request;

class ApiServiceController extends Controller
{
    use DispatcherAware;
    use JsonBodyAware;

    public function sendAssetsInQueue(Request $request)
    {
        $jsonBodyHelper = $this->getJsonBodyHelper();
        $schema = $this->retrieveSchema('assets_enqueue.json');
        $data = $request->getContent();

        $errors = $jsonBodyHelper->validateJson(json_decode($data), $schema);

        if (count($errors) > 0) {
            return Result::createError($request, 422, $errors[0])->createResponse();
        }

        $this->dispatch(WorkerPluginEvents::ASSETS_CREATE, new AssetsCreateEvent(json_decode($data)));

        return Result::create($request, [
            "data" => json_decode($data),
        ])->createResponse();
    }

    private function retrieveSchema($schemaUri)
    {
        $schemaUri = 'file://' . realpath(__DIR__ . '/../../../config/json_schema/'.$schemaUri);
        return $this->app['json-schema.ref_resolver']->resolve($schemaUri);
    }
}
