<?php

namespace Alchemy\WorkerPlugin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class WorkerPluginPullAssetsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('endpoint', 'text', [
                'label' => 'Uploader service endpoint'
            ])
            ->add('clientSecret', 'text', [
                'label' => 'Client secret'
            ])
            ->add('clientId', 'text', [
                'label' => 'Client ID'
            ])
            ->add('pullInterval', 'text', [
                'label' => 'Checking interval in second'
            ])
        ;
    }

    public function getName()
    {
        return 'worker_plugin_pullAssets';
    }
}
