<?php

namespace Alchemy\WorkerPlugin\Provider;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\ControllerProvider\Api\Api;
use Alchemy\Phrasea\Core\Event\Listener\OAuthListener;
use Alchemy\Phrasea\Core\LazyLocator;
use Alchemy\Phrasea\Plugin\BasePluginMetadata;
use Alchemy\Phrasea\Plugin\PluginProviderInterface;
use Alchemy\Phrasea\Security\Firewall;
use Alchemy\WorkerPlugin\Configuration\ConfigurationTab;
use Alchemy\WorkerPlugin\Configuration\MetadataTab;
use Alchemy\WorkerPlugin\Configuration\PullAssetsTab;
use Alchemy\WorkerPlugin\Configuration\SearchengineTab;
use Alchemy\WorkerPlugin\Configuration\SubviewTab;
use Alchemy\WorkerPlugin\Controller\AdminConfigurationController;
use Alchemy\WorkerPlugin\Controller\ApiServiceController;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Security\WorkerPluginConfigurationVoter;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class ControllerServiceProvider extends Api implements PluginProviderInterface
{
    const WORKER_PLUGIN_TEXTDOMAIN = 'worker-plugin';

    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['worker_plugin.name'] = 'phraseanet-plugin-workers';
        $app['worker_plugin.version'] = '1.0.0';

        // register admin tab
        $this->registerConfigurationTabs($app);

        // register voters
        $this->registerVoters($app);

        $app['controller.api.service'] = $app->share(function (PhraseaApplication $app) {
            return (new ApiServiceController($app))
                ->setDispatcher($app['dispatcher'])
                ->setJsonBodyHelper(new LazyLocator($app, 'json.body_helper'));
        });

        $app['controller.admin.configuration'] = $app->share(function (PhraseaApplication $app) {
            return new AdminConfigurationController($app);
        });

        $app->post('/api/v1/upload/enqueue/', 'controller.api.service:sendAssetsInQueue')
            ->before(new OAuthListener());

        $app->post('/webhook', array($this, 'getWebhookData'));

        // register translator resource
        $app['translator'] = $app->share(
            $app->extend('translator', function($translator, $app) {

                $translator->addResource('po',__DIR__ . '/../../../locale/en_GB/worker-plugin.po', 'en', self::WORKER_PLUGIN_TEXTDOMAIN);
                $translator->addResource('po', __DIR__ . '/../../../locale/fr_FR/worker-plugin.po', 'fr', self::WORKER_PLUGIN_TEXTDOMAIN);

                return $translator;
            })
        );

        // register admin route
        $this->registerAdminRoutes($app);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
        /** @var \Pimple $plugins */
        $plugins = $app['plugins'];
        $plugins[$app['worker_plugin.name']] = $plugins->share(function () use ($app) {
            return new BasePluginMetadata(
                $app['worker_plugin.name'],
                $app['worker_plugin.version'],
                '',
                self::WORKER_PLUGIN_TEXTDOMAIN,
                $app['worker_plugin.configuration_tabs']
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function create(PhraseaApplication $app)
    {
        return new static();
    }

    public function getWebhookData(Application $app, Request $request)
    {
        $messagePubliser = $this->getMessagePublisher($app);
        $messagePubliser->pushLog("RECEIVED ON phraseanet WEBHOOK URL TEST = ". $request->getUri() . " DATA : ". $request->getContent());

        return 0;
    }

    /**
     * @param Application $app
     */
    private function registerConfigurationTabs(Application $app)
    {
        $app['worker_plugin.configuration_tabs'] = [
            'searchengine'  => 'worker_plugin.configuration_tabs.searchengine',
            'subview'       => 'worker_plugin.configuration_tabs.subview',
            'metadata'      => 'worker_plugin.configuration_tabs.metadata',
            'configuration' => 'worker_plugin.configuration_tabs.configuration',
            'pullAssets'    => 'worker_plugin.configuration_tabs.pullAssets'
        ];

        $app['worker_plugin.configuration_tabs.configuration'] = $app->share(function (PhraseaApplication $app) {
            return new ConfigurationTab($app['url_generator']);
        });

        $app['worker_plugin.configuration_tabs.searchengine'] = $app->share(function (PhraseaApplication $app) {
            return new SearchengineTab($app['url_generator']);
        });

        $app['worker_plugin.configuration_tabs.subview'] = $app->share(function (PhraseaApplication $app) {
            return new SubviewTab($app['url_generator']);
        });

        $app['worker_plugin.configuration_tabs.metadata'] = $app->share(function (PhraseaApplication $app) {
            return new MetadataTab($app['url_generator']);
        });

        $app['worker_plugin.configuration_tabs.pullAssets'] = $app->share(function (PhraseaApplication $app) {
            return new PullAssetsTab($app['url_generator']);
        });
    }

    /**
     * @param Application $app
     */
    private function registerVoters(Application $app)
    {
        $app['phraseanet.voters'] = $app->share(
            $app->extend('phraseanet.voters', function (array $voters, PhraseaApplication $app) {

                $voters[] = new WorkerPluginConfigurationVoter($app['repo.users']);

                return $voters;
            })
        );
    }

    private function registerAdminRoutes(Application $app)
    {
        /** @var Firewall  $firewall */
        $firewall = $this->getFirewall($app);

        $app->match('/worker-plugin/configuration',  'controller.admin.configuration:configurationAction')
            ->method('GET|POST')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('worker_plugin_admin_configuration');

        $app->match('/worker-plugin/searchengine',  'controller.admin.configuration:searchengineAction')
            ->method('GET|POST')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('worker_plugin_admin_searchengine');

        $app->match('/worker-plugin/subview',  'controller.admin.configuration:subviewAction')
            ->method('GET|POST')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('worker_plugin_admin_subview');

        $app->match('/worker-plugin/metadata',  'controller.admin.configuration:metadataAction')
            ->method('GET|POST')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('worker_plugin_admin_metadata');

        $app->get('/worker-plugin/populate-status',  'controller.admin.configuration:populateStatusAction')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('worker_plugin_admin_populate_status');

        $app->match('/worker-plugin/pull-assets',  'controller.admin.configuration:pullAssetsAction')
            ->method('GET|POST')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('worker_plugin_admin_pullAssets');
    }

    /**
     * @param Application $app
     * @return mixed
     */
    private function getFirewall(Application $app)
    {
        return $app['firewall'];
    }

    /**
     * @param Application $app
     * @return MessagePublisher
     */
    private function getMessagePublisher(Application $app)
    {
        return $app['alchemy_service.message.publisher'];
    }
}
