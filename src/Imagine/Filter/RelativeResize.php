<?php

namespace Anezi\ImagineBundle\Imagine\Filter;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Filter for resizing an image relative to its existing dimensions.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class RelativeResize implements FilterInterface
{
    private $method;
    private $parameter;

    /**
     * Constructs a RelativeResize filter with the given method and argument.
     *
     * @param string $method    BoxInterface method
     * @param mixed  $parameter Parameter for BoxInterface method
     *
     * @throws \Imagine\Exception\InvalidArgumentException
     */
    public function __construct($method, $parameter)
    {
        if (!in_array($method, ['heighten', 'increase', 'scale', 'widen'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported method: ', $method));
        }

        $this->method = $method;
        $this->parameter = $parameter;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return $image->resize(call_user_func([$image->getSize(), $this->method], $this->parameter));
    }
}
