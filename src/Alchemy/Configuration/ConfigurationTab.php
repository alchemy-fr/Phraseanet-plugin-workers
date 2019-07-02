<?php

namespace Alchemy\WorkerPlugin\Configuration;

use Alchemy\Phrasea\Plugin\ConfigurationTabInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class ConfigurationTab
 * @package Alchemy\CarrickPlugin\Configuration
 */
class ConfigurationTab implements ConfigurationTabInterface
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function getTitle()
    {
        return 'configuration_tab';
    }

    public function getUrl()
    {
        return $this->urlGenerator->generate('worker_plugin_admin_configuration');
    }
}
