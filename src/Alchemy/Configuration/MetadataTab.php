<?php

namespace Alchemy\WorkerPlugin\Configuration;

use Alchemy\Phrasea\Plugin\ConfigurationTabInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MetadataTab implements ConfigurationTabInterface
{

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Get the title translation key in plugin domain
     *
     * @return string
     */
    public function getTitle()
    {
        return 'Metadata';
    }

    /**
     * Get the url where metadata tab can be retrieved
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->urlGenerator->generate('worker_plugin_admin_metadata');
    }
}
