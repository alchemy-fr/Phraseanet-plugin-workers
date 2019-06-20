<?php

namespace Alchemy\WorkerPlugin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class WorkerPluginConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('url_uploader_service', 'text', [
                'label' => 'Url uploader service',
                'attr' => ['class' => 'input-xxlarge']
            ])
        ;
    }

    public function getName()
    {
        return 'worker_plugin_configuration';
    }
}
