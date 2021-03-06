<?php

namespace Anezi\ImagineBundle\DependencyInjection\Factory\Loader;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface LoaderFactoryInterface
{
    /**
     * @param ContainerBuilder $container
     * @param string           $loaderName
     * @param array            $config
     *
     * @return string The resolver service id
     */
    public function create(ContainerBuilder $container, $loaderName, array $config);

    /**
     * The resolver factory name,
     * For example filesystem, stream.
     *
     * @return string
     */
    public function getName();

    /**
     * @param ArrayNodeDefinition $builder
     */
    public function addConfiguration(ArrayNodeDefinition $builder);
}
