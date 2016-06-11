<?php

namespace Anezi\ImagineBundle\Controller;

use Imagine\Exception\RuntimeException;
use Anezi\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Anezi\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ImagineController.
 */
class ImagineController extends Controller
{
    /**
     * This action applies a given filter to a given image, optionally saves the image and outputs it to the browser at the same time.
     *
     * @Route(
     *     path="/media/cache/resolve/{loader}/{filter}/{path}",
     *     name="anezi_imagine_resolve",
     *     requirements={"filter"="[A-z0-9_\-]*","path"=".+"}
     * )
     *
     * @param Request $request
     * @param string  $loader
     * @param string  $filter
     * @param string  $path
     *
     * @return RedirectResponse
     */
    public function resolveAction(Request $request, string $loader, string $filter, string $path) : RedirectResponse
    {
        // decoding special characters and whitespaces from path obtained from url
        $path = urldecode($path);
        $resolver = $request->get('resolver');

        try {
            $cacheManager = $this->get('anezi_imagine.cache.manager');

            if (!$cacheManager->isStored($path, $filter, $resolver)) {
                $dataManager = $this->get('anezi_imagine.data.manager');

                try {
                    $binary = $dataManager->find($dataManager->getLoader($loader), $path);
                } catch (NotLoadableException $e) {
                    $defaultImageUrl = $dataManager->getDefaultImageUrl($filter);

                    if ($defaultImageUrl) {
                        return new RedirectResponse($defaultImageUrl);
                    }

                    throw new NotFoundHttpException('Source image could not be found', $e);
                }

                $cacheManager->store(
                    $this->get('anezi_imagine.filter.manager')->applyFilter($binary, $filter),
                    $path,
                    $filter,
                    $resolver
                );
            }

            return new RedirectResponse($cacheManager->resolve($path, $filter, $resolver), 301);
        } catch (NonExistingFilterException $e) {
            $message = sprintf('Could not locate filter "%s" for path "%s". Message was "%s"', $filter, $path, $e->getMessage());

            if ($this->has('logger')) {
                $this->get('logger')->debug($message);
            }

            throw new NotFoundHttpException($message, $e);
        } catch (RuntimeException $e) {
            throw new \RuntimeException(sprintf('Unable to create image for path "%s" and filter "%s". Message was "%s"', $path, $filter, $e->getMessage()), 0, $e);
        }
    }

    /**
     * This action applies a given filter to a given image, optionally saves the image and outputs it to the browser at the same time.
     *
     * @Route(
     *     path="/media/cache/resolve/{loader}/{filter}/rc/{hash}/{path}",
     *     name="anezi_imagine_filter_runtime",
     *     requirements={"filter"="[A-z0-9_\-]*","path"=".+"}
     * )
     * @Method("GET")
     *
     * @param Request $request
     * @param string  $loader
     * @param string  $hash
     * @param string  $path
     * @param string  $filter
     *
     * @return RedirectResponse
     */
    public function filterRuntimeAction(Request $request, string $loader, string $hash, string $path, string $filter) : RedirectResponse
    {
        $resolver = $request->get('resolver');

        try {
            $filters = $request->query->get('filters', []);

            if (!is_array($filters)) {
                throw new NotFoundHttpException(sprintf('Filters must be an array. Value was "%s"', $filters));
            }

            if (true !== $this->get('anezi_imagine.cache.signer')->check($hash, $path, $filters)) {
                throw new BadRequestHttpException(sprintf(
                    'Signed url does not pass the sign check for path "%s" and filter "%s" and runtime config %s',
                    $path,
                    $filter,
                    json_encode($filters)
                ));
            }

            $dataManager = $this->get('anezi_imagine.data.manager');

            try {
                $binary = $dataManager->find($dataManager->getLoader($loader), $path);
            } catch (NotLoadableException $e) {
                $defaultImageUrl = $dataManager->getDefaultImageUrl($filter);

                if ($defaultImageUrl) {
                    return new RedirectResponse($defaultImageUrl);
                }

                throw new NotFoundHttpException(sprintf('Source image could not be found for path "%s" and filter "%s"', $path, $filter), $e);
            }

            $cacheManager = $this->get('anezi_imagine.cache.manager');

            $rcPath = $cacheManager->getRuntimePath($path, $filters);

            $cacheManager->store(
                $this->get('anezi_imagine.filter.manager')->applyFilter($binary, $filter, [
                    'filters' => $filters,
                ]),
                $rcPath,
                $filter,
                $resolver
            );

            return new RedirectResponse($cacheManager->resolve($rcPath, $filter, $resolver), 301);
        } catch (NonExistingFilterException $e) {
            $message = sprintf('Could not locate filter "%s" for path "%s". Message was "%s"', $filter, $hash.'/'.$path, $e->getMessage());

            if ($this->has('logger')) {
                $this->get('logger')->debug($message);
            }

            throw new NotFoundHttpException($message, $e);
        } catch (RuntimeException $e) {
            throw new \RuntimeException(sprintf('Unable to create image for path "%s" and filter "%s". Message was "%s"', $hash.'/'.$path, $filter, $e->getMessage()), 0, $e);
        }
    }
}
