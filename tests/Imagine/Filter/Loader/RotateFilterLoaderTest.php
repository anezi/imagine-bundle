<?php

namespace Anezi\ImagineBundle\Tests\Filter;

use Anezi\ImagineBundle\Imagine\Filter\Loader\RotateFilterLoader;
use Anezi\ImagineBundle\Tests\AbstractTest;

/**
 * Test cases for RotateFilterLoader class.
 *
 * @covers Anezi\ImagineBundle\Imagine\Filter\Loader\RotateFilterLoader
 *
 * @author Bocharsky Victor <bocharsky.bw@gmail.com>
 */
class RotateFilterLoaderTest extends AbstractTest
{
    public function testLoadRotate0Degrees()
    {
        $loader = new RotateFilterLoader();

        $image = $this->getMockImage();

        $result = $loader->load($image, ['angle' => 0]);
        $this->assertSame($image, $result);
    }

    public function testLoadRotate90Degrees()
    {
        $loader = new RotateFilterLoader();

        $image = $this->getMockImage();
        $rotatedImage = $this->getMockImage();

        $image
            ->expects($this->once())
            ->method('rotate')
            ->with(90)
            ->willReturn($rotatedImage);

        $result = $loader->load($image, ['angle' => 90]);
        $this->assertSame($rotatedImage, $result);
    }
}
