<?php

namespace Anezi\ImagineBundle\Twig\Helper;

use Anezi\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Templating\Helper\Helper;

class ImagineHelper extends Helper
{
    /**
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * @param CacheManager $cacheManager
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Gets the browser path for the image and filter to apply.
     *
     * @param string $path
     * @param string $filter
     * @param array  $runtimeConfig
     *
     * @return string
     */
    public function filter($path, $filter, array $runtimeConfig = [])
    {
        return $this->cacheManager->getBrowserPath($path, $filter, $runtimeConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'anezi_imagine';
    }
}
