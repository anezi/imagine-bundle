<?php

namespace Anezi\ImagineBundle\Imagine\Cache;

use Anezi\ImagineBundle\Binary\BinaryInterface;
use Anezi\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Anezi\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Anezi\ImagineBundle\ImagineEvents;
use Anezi\ImagineBundle\Events\CacheResolveEvent;

/**
 * Class CacheManager.
 */
class CacheManager
{
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
     * @param FilterConfiguration      $filterConfig
     * @param RouterInterface          $router
     * @param SignerInterface          $signer
     * @param EventDispatcherInterface $dispatcher
     * @param string                   $defaultResolver
     */
    public function __construct(
        FilterConfiguration $filterConfig,
        RouterInterface $router,
        SignerInterface $signer,
        EventDispatcherInterface $dispatcher,
        $defaultResolver = null
    ) {
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
     * @param string $resolver
     *
     * @return ResolverInterface
     *
     * @throws \OutOfBoundsException If neither a specific nor a default resolver is available.
     */
    protected function getResolver($filter, $resolver)
    {
        // BC
        if (false == $resolver) {
            $config = $this->filterConfig->get($filter);

            $resolverName = empty($config['cache']) ? $this->defaultResolver : $config['cache'];
        } else {
            $resolverName = $resolver;
        }

        if (!isset($this->resolvers[$resolverName])) {
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
     * @param string $path          The path where the resolved file is expected.
     * @param string $filter
     * @param array  $runtimeConfig
     * @param string $resolver
     *
     * @return string
     */
    public function getBrowserPath($path, $filter, array $runtimeConfig = [], $resolver = null)
    {
        if (!empty($runtimeConfig)) {
            $rcPath = $this->getRuntimePath($path, $runtimeConfig);

            return $this->isStored($rcPath, $filter, $resolver) ?
                $this->resolve($rcPath, $filter, $resolver) :
                $this->generateUrl($path, $filter, $runtimeConfig, $resolver)
            ;
        }

        return $this->isStored($path, $filter, $resolver) ?
            $this->resolve($path, $filter, $resolver) :
            $this->generateUrl($path, $filter, [], $resolver)
        ;
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
     * @param string $path          The path where the resolved file is expected.
     * @param string $filter        The name of the imagine filter in effect.
     * @param array  $runtimeConfig
     * @param string $resolver
     *
     * @return string
     */
    public function generateUrl($path, $filter, array $runtimeConfig = [], $resolver = null)
    {
        $params = [
            'path' => ltrim($path, '/'),
            'filter' => $filter,
        ];

        if ($resolver) {
            $params['resolver'] = $resolver;
        }

        if (empty($runtimeConfig)) {
            $filterUrl = $this->router->generate('anezi_imagine_resolve', $params, UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            $params['filters'] = $runtimeConfig;
            $params['hash'] = $this->signer->sign($path, $runtimeConfig);

            $filterUrl = $this->router->generate('anezi_imagine_filter_runtime', $params, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $filterUrl;
    }

    /**
     * Checks whether the path is already stored within the respective Resolver.
     *
     * @param string $path
     * @param string $filter
     * @param string $resolver
     *
     * @return bool
     */
    public function isStored($path, $filter, $resolver = null)
    {
        return $this->getResolver($filter, $resolver)->isStored($path, $filter);
    }

    /**
     * Resolves filtered path for rendering in the browser.
     *
     * @param string $path
     * @param string $filter
     * @param string $resolver
     *
     * @return string The url of resolved image.
     *
     * @throws NotFoundHttpException if the path can not be resolved
     */
    public function resolve($path, $filter, $resolver = null)
    {
        if (false !== strpos($path, '/../') || 0 === strpos($path, '../')) {
            throw new NotFoundHttpException(sprintf("Source image was searched with '%s' outside of the defined root path", $path));
        }

        $preEvent = new CacheResolveEvent($path, $filter);
        $this->dispatcher->dispatch(ImagineEvents::PRE_RESOLVE, $preEvent);

        $url = $this->getResolver($preEvent->getFilter(), $resolver)->resolve($preEvent->getPath(), $preEvent->getFilter());

        $postEvent = new CacheResolveEvent($preEvent->getPath(), $preEvent->getFilter(), $url);
        $this->dispatcher->dispatch(ImagineEvents::POST_RESOLVE, $postEvent);

        return $postEvent->getUrl();
    }

    /**
     * @see ResolverInterface::store
     *
     * @param BinaryInterface $binary
     * @param string          $path
     * @param string          $filter
     * @param string          $resolver
     */
    public function store(BinaryInterface $binary, $path, $filter, $resolver = null)
    {
        $this->getResolver($filter, $resolver)->store($binary, $path, $filter);
    }

    /**
     * @param string|string[]|null $paths
     * @param string|string[]|null $filters
     */
    public function remove($paths = null, $filters = null)
    {
        if (null === $filters) {
            $filters = array_keys($this->filterConfig->all());
        }
        if (!is_array($filters)) {
            $filters = [$filters];
        }
        if (!is_array($paths)) {
            $paths = [$paths];
        }

        $paths = array_filter($paths);
        $filters = array_filter($filters);

        $mapping = new \SplObjectStorage();
        foreach ($filters as $filter) {
            $resolver = $this->getResolver($filter, null);

            $list = isset($mapping[$resolver]) ? $mapping[$resolver] : [];

            $list[] = $filter;

            $mapping[$resolver] = $list;
        }

        foreach ($mapping as $resolver) {
            $resolver->remove($paths, $mapping[$resolver]);
        }
    }
}
