<?php

namespace Anezi\ImagineBundle\Imagine\Filter\PostProcessor;

use Anezi\ImagineBundle\Binary\BinaryInterface;
use Anezi\ImagineBundle\Binary\FileBinaryInterface;
use Anezi\ImagineBundle\Model\Binary;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;

class JpegOptimPostProcessor implements PostProcessorInterface
{
    /** @var string Path to jpegoptim binary */
    protected $jpegoptimBin;

    /**
     * If set --strip-all will be passed to jpegoptim.
     *
     * @var bool
     */
    protected $stripAll = true;

    /**
     * If set, --max=$value will be passed to jpegoptim.
     *
     * @var int
     */
    protected $max;

    /**
     * If set to true --all-progressive will be passed to jpegoptim, otherwise --all-normal will be passed.
     *
     * @var bool
     */
    protected $progressive = true;

    /**
     * Constructor.
     *
     * @param string $jpegoptimBin Path to the jpegoptim binary
     */
    public function __construct($jpegoptimBin = '/usr/bin/jpegoptim')
    {
        $this->jpegoptimBin = $jpegoptimBin;
    }

    /**
     * @param int $max
     *
     * @return JpegOptimPostProcessor
     */
    public function setMax($max)
    {
        $this->max = $max;

        return $this;
    }

    /**
     * @param bool $progressive
     *
     * @return JpegOptimPostProcessor
     */
    public function setProgressive($progressive)
    {
        $this->progressive = $progressive;

        return $this;
    }

    /**
     * @param bool $stripAll
     *
     * @return JpegOptimPostProcessor
     */
    public function setStripAll($stripAll)
    {
        $this->stripAll = $stripAll;

        return $this;
    }

    /**
     * @param BinaryInterface $binary
     *
     * @throws ProcessFailedException
     *
     * @return BinaryInterface
     *
     * @see      Implementation taken from Assetic\Filter\JpegoptimFilter
     */
    public function process(BinaryInterface $binary)
    {
        $type = strtolower($binary->getMimeType());
        if (!in_array($type, ['image/jpeg', 'image/jpg'], true)) {
            return $binary;
        }

        if (false === $input = tempnam(sys_get_temp_dir(), 'imagine_jpegoptim')) {
            throw new \RuntimeException(sprintf('Temp file can not be created in "%s".', sys_get_temp_dir()));
        }

        $pb = new ProcessBuilder([$this->jpegoptimBin]);

        if ($this->stripAll) {
            $pb->add('--strip-all');
        }

        if ($this->max) {
            $pb->add('--max='.$this->max);
        }

        if ($this->progressive) {
            $pb->add('--all-progressive');
        } else {
            $pb->add('--all-normal');
        }

        $pb->add($input);
        if ($binary instanceof FileBinaryInterface) {
            copy($binary->getPath(), $input);
        } else {
            file_put_contents($input, $binary->getContent());
        }

        $proc = $pb->getProcess();
        $proc->run();

        if (false !== strpos($proc->getOutput(), 'ERROR') || 0 !== $proc->getExitCode()) {
            unlink($input);
            throw new ProcessFailedException($proc);
        }

        $result = new Binary(file_get_contents($input), $binary->getMimeType(), $binary->getFormat());

        unlink($input);

        return $result;
    }
}
