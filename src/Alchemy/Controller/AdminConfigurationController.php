<?php

namespace Alchemy\WorkerPlugin\Controller;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\Phrasea\SearchEngine\Elastic\ElasticsearchOptions;
use Alchemy\WorkerPlugin\Configuration\Config;
use Alchemy\WorkerPlugin\Event\PopulateIndexEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Form\WorkerPluginConfigurationType;
use Alchemy\WorkerPlugin\Form\WorkerPluginPullAssetsType;
use Alchemy\WorkerPlugin\Form\WorkerPluginSearchengineType;
use Alchemy\WorkerPlugin\Model\DBManipulator;
use Alchemy\WorkerPlugin\Queue\AMQPConnection;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class AdminConfigurationController extends Controller
{
    /**
     * @param PhraseaApplication $app
     * @param Request $request
     * @return mixed
     */
    public function configurationAction(PhraseaApplication $app, Request $request)
    {
        $retryQueueConfig = $this->getRetryQueueConfiguration();

        $form = $app->form(new WorkerPluginConfigurationType(), $retryQueueConfig);

        $form->handleRequest($request);

        if ($form->isValid()) {
            // only changed value in the form
            $newTtlChanged = array_diff_assoc($form->getData(), $retryQueueConfig);

            if ($newTtlChanged) {
                Config::setConfiguration(['retry_queue' => $form->getData()]);

                $queues = array_intersect_key(AMQPConnection::$defaultQueues, $newTtlChanged);
                $retryQueuesToReset = array_intersect_key(AMQPConnection::$defaultRetryQueues, array_flip($queues));

                /** @var AMQPConnection $serverConnection */
                $serverConnection = $this->app['alchemy_service.amqp.connection'];

                $serverConnection->reinitializeQueue($retryQueuesToReset);
            }

            return $app->redirectPath('admin_plugins_list');
        }

        return $app['twig']->render('phraseanet-plugin-workers/admin/worker_plugin_configuration.html.twig', [
            'form' => $form->createView()
        ]);
    }

    public function searchengineAction(PhraseaApplication $app, Request $request)
    {
        $options = $this->getElasticsearchOptions();

        $form = $app->form(new WorkerPluginSearchengineType(), $options);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $populateInfo = $this->getData($form);

            $this->getDispatcher()->dispatch(WorkerPluginEvents::POPULATE_INDEX, new PopulateIndexEvent($populateInfo));

            return $app->redirectPath('admin_plugins_list');
        }

        return $app['twig']->render('phraseanet-plugin-workers/admin/worker_plugin_searchengine.html.twig', [
            'form' => $form->createView()
        ]);
    }

    public function subviewAction(PhraseaApplication $app)
    {
        return $app['twig']->render('phraseanet-plugin-workers/admin/worker_plugin_subview.html.twig', [
        ]);
    }

    public function metadataAction(PhraseaApplication $app)
    {
        return $app['twig']->render('phraseanet-plugin-workers/admin/worker_plugin_metadata.html.twig', [
        ]);
    }

    public function populateStatusAction(PhraseaApplication $app, Request $request)
    {
        $databoxIds = $request->get('sbasIds');

        return DBManipulator::checkPopulateIndexStatusByDataboxId($databoxIds);
    }

    public function pullAssetsAction(PhraseaApplication $app, Request $request)
    {
        $pullAssetsConfig = $this->getPullAssetsConfiguration();
        $form = $app->form(new WorkerPluginPullAssetsType(), $pullAssetsConfig);

        $form->handleRequest($request);
        if ($form->isValid()) {
            Config::setConfiguration(['pull_assets' => $form->getData()]);

            /** @var AMQPConnection $serverConnection */
            $serverConnection = $this->app['alchemy_service.amqp.connection'];
            // reinitialize the pull queues
            $serverConnection->reinitializeQueue([MessagePublisher::PULL_QUEUE]);
            $this->app['alchemy_service.message.publisher']->initializePullAssets();

            return $app->redirectPath('admin_plugins_list');
        }

        return $app['twig']->render('phraseanet-plugin-workers/admin/worker_plugin_pull_assets.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @return EventDispatcherInterface
     */
    private function getDispatcher()
    {
        return $this->app['dispatcher'];
    }

    /**
     * @return ElasticsearchOptions
     */
    private function getElasticsearchOptions()
    {
        return $this->app['elasticsearch.options'];
    }

    /**
     * @param FormInterface $form
     * @return array
     */
    private function getData(FormInterface $form)
    {
        /** @var ElasticsearchOptions $options */
        $options = $form->getData();

        $data['host'] = $options->getHost();
        $data['port'] = $options->getPort();
        $data['indexName'] = $options->getIndexName();
        $data['databoxIds'] = $form->getExtraData()['sbas'];

        return $data;
    }

    private function getPullAssetsConfiguration()
    {
        $config = Config::getConfiguration();

        if (isset($config['worker_plugin']) && isset($config['worker_plugin']['pull_assets'])) {
                return $config['worker_plugin']['pull_assets'];
        }

        return [];
    }

    private function getRetryQueueConfiguration()
    {
        $config = Config::getConfiguration();

        if (isset($config['worker_plugin']) && isset($config['worker_plugin']['retry_queue'])) {
            return $config['worker_plugin']['retry_queue'];
        }

        return [];
    }
}
