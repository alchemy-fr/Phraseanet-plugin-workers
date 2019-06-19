<?php

namespace Alchemy\WorkerPlugin\Controller;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\WorkerPlugin\Configuration\Config;
use Alchemy\WorkerPlugin\Form\WorkerPluginConfigurationType;
use Symfony\Component\HttpFoundation\Request;

class AdminConfigurationController extends Controller
{
    /**
     * @param PhraseaApplication $app
     * @param Request $request
     * @return mixed
     */
    public function configuration(PhraseaApplication $app, Request $request)
    {
        $config = Config::getConfiguration();

        $form = $app->form(new WorkerPluginConfigurationType(), $config['worker_plugin']);

        $form->handleRequest($request);

        if ($form->isValid()) {
            Config::setConfiguration($form->getData());

            return $app->redirectPath('admin_plugins_list');
        }

        return $app['twig']->render('phraseanet-plugin-services/admin/worker_plugin_configuration.html.twig',[
            'form' => $form->createView()
        ]);
    }
}
