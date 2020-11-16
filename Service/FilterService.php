<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Service;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FilterService
{
    /**
     * @var DataManager
     */
    private $dataManager;

    /**
     * @var FilterManager
     */
    private $filterManager;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $webpGenerate;

    /**
     * @var int
     */
    private $webpQuality;

    /**
     * @param DataManager     $dataManager
     * @param FilterManager   $filterManager
     * @param CacheManager    $cacheManager
     * @param bool            $webpGenerate
     * @param int             $webpQuality
     * @param LoggerInterface $logger
     */
    public function __construct(
        DataManager $dataManager,
        FilterManager $filterManager,
        CacheManager $cacheManager,
        $webpGenerate,
        $webpQuality,
        LoggerInterface $logger = null
    ) {
        $this->dataManager = $dataManager;
        $this->filterManager = $filterManager;
        $this->cacheManager = $cacheManager;
        $this->webpGenerate = $webpGenerate;
        $this->webpQuality = $webpQuality;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param string $path
     * @param string $filter
     */
    public function bustCache($path, $filter)
    {
        if (!$this->cacheManager->isStored($path, $filter)) {
            return;
        }

        $this->cacheManager->remove($path, $filter);
    }

    /**
     * @param string $path
     * @param string $filter
     * @param string $resolver
     * @param bool   $webp
     *
     * @return string
     */
    public function getUrlOfFilteredImage($path, $filter, $resolver = null, $webp = false)
    {
        if ($this->cacheManager->isStored($path, $filter, $resolver)) {
            return $this->cacheManager->resolve($path, $filter, $resolver);
        }

        $filteredBinary = $this->createFilteredBinary(
            $path,
            $filter
        );

        $this->cacheManager->store(
            $filteredBinary,
            $path,
            $filter,
            $resolver
        );

        if ($this->webpGenerate) {
            // add webp in new location
            $filteredWebpBinary = $this->createFilteredWebpBinary($path, $filter);
            $this->cacheManager->store($filteredWebpBinary, $path.'.webp', $filter, $resolver);

            if ($webp) {
                return $this->cacheManager->resolve($path.'.webp', $filter, $resolver);
            }
        }

        return $this->cacheManager->resolve($path, $filter, $resolver);
    }

    /**
     * @param string      $path
     * @param string      $filter
     * @param array       $runtimeFilters
     * @param string|null $resolver
     * @param bool        $webp
     *
     * @return string
     */
    public function getUrlOfFilteredImageWithRuntimeFilters(
        $path,
        $filter,
        array $runtimeFilters = [],
        $resolver = null,
        $webp = false
    ) {
        $runtimePath = $this->cacheManager->getRuntimePath($path, $runtimeFilters);
        if ($this->cacheManager->isStored($runtimePath, $filter, $resolver)) {
            return $this->cacheManager->resolve($runtimePath, $filter, $resolver);
        }

        $filteredBinary = $this->createFilteredBinary(
            $path,
            $filter,
            $runtimeFilters
        );

        $this->cacheManager->store(
            $filteredBinary,
            $runtimePath,
            $filter,
            $resolver
        );

        if ($this->webpGenerate) {
            // add webp in new location
            $filteredWebpBinary = $this->createFilteredWebpBinary($path, $filter);
            $this->cacheManager->store($filteredWebpBinary, $runtimePath.'.webp', $filter, $resolver);

            if ($webp) {
                return $this->cacheManager->resolve($runtimePath.'.webp', $filter, $resolver);
            }
        }

        return $this->cacheManager->resolve($runtimePath, $filter, $resolver);
    }

    /**
     * @param string $path
     * @param string $filter
     * @param array  $runtimeFilters
     *
     * @throws NonExistingFilterException
     *
     * @return BinaryInterface
     */
    private function createFilteredBinary($path, $filter, array $runtimeFilters = [])
    {
        $binary = $this->dataManager->find($filter, $path);

        try {
            return $this->filterManager->applyFilter($binary, $filter, [
                'filters' => $runtimeFilters,
            ]);
        } catch (NonExistingFilterException $e) {
            $message = sprintf('Could not locate filter "%s" for path "%s". Message was "%s"', $filter, $path, $e->getMessage());

            $this->logger->debug($message);

            throw $e;
        }
    }

    /**
     * @param string $path
     * @param string $filter
     * @param array  $runtimeFilters
     *
     * @throws NonExistingFilterException
     *
     * @return BinaryInterface
     */
    private function createFilteredWebpBinary($path, $filter, array $runtimeFilters = [])
    {
        $binary = $this->dataManager->find($filter, $path);

        try {
            return $this->filterManager->applyFilter($binary, $filter, [
                'quality' => $this->webpQuality,
                'format' => 'webp',
                'filters' => $runtimeFilters,
            ]);
        } catch (NonExistingFilterException $e) {
            $message = sprintf('Could not locate filter "%s" for path "%s". Message was "%s"', $filter, $path, $e->getMessage());

            $this->logger->debug($message);

            throw $e;
        }
    }
}
