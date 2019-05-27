<?php

namespace Alchemy\WorkerPlugin\Provider;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\ControllerProvider\Api\Api;
use Alchemy\Phrasea\Core\Event\Listener\OAuthListener;
use Alchemy\Phrasea\Core\LazyLocator;
use Alchemy\Phrasea\Plugin\PluginProviderInterface;
use Alchemy\WorkerPlugin\Controller\ApiServiceController;
use Silex\Application;

class ControllerServiceProvider extends Api implements PluginProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['controller.api.service'] = $app->share(function (PhraseaApplication $app) {
            return (new ApiServiceController($app))
                ->setDispatcher($app['dispatcher'])
                ->setJsonBodyHelper(new LazyLocator($app, 'json.body_helper'));
        });

        $app->post('/api/v1/upload/enqueue/', 'controller.api.service:sendAssetsInQueue')
            ->before(new OAuthListener());
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {

    }

    /**
     * {@inheritdoc}
     */
    public static function create(PhraseaApplication $app)
    {
        return new static();
    }

}
