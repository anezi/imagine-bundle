<?php

namespace Anezi\ImagineBundle\tests\Functional\Imagine\Filter\Loader;

use Anezi\ImagineBundle\Tests\Functional\WebTestCase;

class DownscaleFilterLoaderTest extends WebTestCase
{
    public function testCouldBeGetFromContainerAsService()
    {
        $this->createClient();
        $service = self::$kernel->getContainer()->get('anezi_imagine.filter.loader.downscale');

        $this->assertInstanceOf('Anezi\ImagineBundle\Imagine\Filter\Loader\DownscaleFilterLoader', $service);
    }
}
