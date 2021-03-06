<?php

namespace Anezi\ImagineBundle\Imagine\Cache;

use Anezi\ImagineBundle\Binary\BinaryInterface;
use Anezi\ImagineBundle\Events\CacheResolveEvent;
use Anezi\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Anezi\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Anezi\ImagineBundle\Imagine\Filter\FilterManager;
use Anezi\ImagineBundle\ImagineEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class CacheManager.
 */
class CacheManager
{
    /**
     * @var FilterManager
     */
    private $filterManager;

    /**
     * @var FilterConfiguration
     */
    protected $filterConfig;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var ResolverInterface[]
     */
    protected $resolvers = [];

    /**
     * @var SignerInterface
     */
    protected $signer;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var string
     */
    protected $defaultResolver;

    /**
     * Constructs the cache manager to handle Resolvers based on the provided FilterConfiguration.
     *
     * @param FilterManager            $filterManager
     * @param FilterConfiguration      $filterConfig
     * @param RouterInterface          $router
     * @param SignerInterface          $signer
     * @param EventDispatcherInterface $dispatcher
     * @param string                   $defaultResolver
     */
    public function __construct(
        FilterManager $filterManager,
        FilterConfiguration $filterConfig,
        RouterInterface $router,
        SignerInterface $signer,
        EventDispatcherInterface $dispatcher,
        $defaultResolver = null
    ) {
        $this->filterManager = $filterManager;
        $this->filterConfig = $filterConfig;
        $this->router = $router;
        $this->signer = $signer;
        $this->dispatcher = $dispatcher;
        $this->defaultResolver = $defaultResolver ?: 'default';
    }

    /**
     * Adds a resolver to handle cached images for the given filter.
     *
     * @param string            $filter
     * @param ResolverInterface $resolver
     */
    public function addResolver($filter, ResolverInterface $resolver)
    {
        $this->resolvers[$filter] = $resolver;

        if ($resolver instanceof CacheManagerAwareInterface) {
            $resolver->setCacheManager($this);
        }
    }

    /**
     * Gets a resolver for the given filter.
     *
     * In case there is no specific resolver, but a default resolver has been configured, the default will be returned.
     *
     * @param string $filter
     *
     * @throws \OutOfBoundsException If neither a specific nor a default resolver is available.
     *
     * @return ResolverInterface
     */
    protected function getResolver($filter)
    {
        $config = $this->filterConfig->get($filter);

        $resolverName = empty($config['cache']) ? $this->defaultResolver : $config['cache'];

        if (isset($this->resolvers[$resolverName]) === false) {
            throw new \OutOfBoundsException(sprintf(
                'Could not find resolver "%s" for "%s" filter type',
                $resolverName,
                $filter
            ));
        }

        return $this->resolvers[$resolverName];
    }

    /**
     * Gets filtered path for rendering in the browser.
     * It could be the cached one or an url of filter action.
     *
     * @param string $path The path where the resolved file is expected.
     * @param string $loader
     * @param string $filter
     *
     * @return string
     */
    public function getBrowserPath(string $path, string $loader, string $filter)
    {
        return $this->generateUrl($path, $loader, $filter);
    }

    /**
     * Get path to runtime config image.
     *
     * @param string $path
     * @param array  $runtimeConfig
     *
     * @return string
     */
    public function getRuntimePath($path, array $runtimeConfig)
    {
        return 'rc/'.$this->signer->sign($path, $runtimeConfig).'/'.$path;
    }

    /**
     * Returns a web accessible URL.
     *
     * @param string $path   The path where the resolved file is expected.
     * @param string $loader
     * @param string $filter The name of the imagine filter in effect.
     *
     * @return string
     */
    public function generateUrl(string $path, string $loader, string $filter)
    {
        $params = [
            'path' => ltrim($path, '/'),
            'filter' => $filter,
            'loader' => $loader,
        ];

        $filterUrl = $this->router->generate('anezi_imagine_load', $params, UrlGeneratorInterface::ABSOLUTE_URL);

        return $filterUrl;
    }

    /**
     * Checks whether the path is already stored within the respective Resolver.
     *
     * @param string $path
     * @param string $loader
     * @param string $filter
     *
     * @return bool
     */
    public function isStored(string $path, string $loader, string $filter)
    {
        return $this->getResolver($filter)->isStored($path, $loader, $filter);
    }

    /**
     * Resolves filtered path for rendering in the browser.
     *
     * @param string $path
     * @param string $loader
     * @param string $filter
     *
     * @return string The url of resolved image.
     */
    public function resolve(string $path, string $loader, string $filter)
    {
        if (false !== strpos($path, '/../') || 0 === strpos($path, '../')) {
            throw new NotFoundHttpException(sprintf("Source image was searched with '%s' outside of the defined root path", $path));
        }

        $preEvent = new CacheResolveEvent($path, $loader, $filter);
        $this->dispatcher->dispatch(ImagineEvents::PRE_RESOLVE, $preEvent);

        $url = $this->getResolver($preEvent->getFilter())
            ->resolve($preEvent->getPath(), $preEvent->getLoader(), $preEvent->getFilter()
        );

        $postEvent = new CacheResolveEvent($preEvent->getPath(), $preEvent->getFilter(), $url);
        $this->dispatcher->dispatch(ImagineEvents::POST_RESOLVE, $postEvent);

        return $postEvent->getUrl();
    }

    /**
     * @see ResolverInterface::store
     *
     * @param BinaryInterface $binary
     * @param string          $path
     * @param string          $loader
     * @param string          $filter
     */
    public function store(BinaryInterface $binary, string $path, string $loader, string $filter)
    {
        $this->getResolver($filter)->store($binary, $path, $loader, $filter);
    }

    /**
     * @param string|string[]|null $paths
     * @param string|string[]|null $loaders
     * @param string|string[]|null $filters
     */
    public function remove($paths = null, $loaders = null, $filters = null)
    {
        if (null === $filters) {
            $filters = array_keys($this->filterConfig->all());
        }

        if (!is_array($filters)) {
            $filters = [$filters];
        }

        if (null === $loaders) {
            $loaders = array_keys($this->filterManager->getLoaders());
        }

        if (!is_array($loaders)) {
            $loaders = [$loaders];
        }

        if (!is_array($paths)) {
            $paths = [$paths];
        }

        $paths = array_filter($paths);
        $filters = array_filter($filters);
        $loaders = array_filter($loaders);

        $mapping = new \SplObjectStorage();

        foreach ($filters as $filter) {
            $resolver = $this->getResolver($filter);

            $list = isset($mapping[$resolver]) ? $mapping[$resolver] : [];

            $list[] = $filter;

            $mapping[$resolver] = $list;
        }

        /** @var ResolverInterface $resolver */
        foreach ($mapping as $resolver) {
            $resolver->remove($paths, $loaders, $mapping[$resolver]);
        }
    }

    /**
     * @param string $path
     * @param string $loader
     * @param string $filter
     *
     * @return string
     */
    public function fetch(string $path, string $loader, string $filter)
    {
        return $this->getResolver($filter)->fetch($path, $loader, $filter);
    }
}
