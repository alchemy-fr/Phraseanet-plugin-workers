<?php

namespace Alchemy\WorkerPlugin\Controller;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\Phrasea\SearchEngine\Elastic\ElasticsearchOptions;
use Alchemy\WorkerPlugin\Configuration\Config;
use Alchemy\WorkerPlugin\Event\PopulateIndexEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Form\WorkerPluginConfigurationType;
use Alchemy\WorkerPlugin\Form\WorkerPluginSearchengineType;
use Alchemy\WorkerPlugin\Model\DBManipulator;
use Alchemy\WorkerPlugin\Queue\AMQPConnection;
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
        $config = Config::getConfiguration();

        $form = $app->form(new WorkerPluginConfigurationType(), $config['worker_plugin']);

        $form->handleRequest($request);

        if ($form->isValid()) {
            // only changed value in the form
            $config = isset($config['worker_plugin']) ? $config['worker_plugin'] : [];
            $newTtlChanged = array_diff_assoc($form->getData(), $config);

            if ($newTtlChanged) {
                Config::setConfiguration($form->getData());

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
}
