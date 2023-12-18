<?php

namespace LaminasBench\Cache;

use Laminas\Cache\Storage\Adapter\Benchmark\AbstractStorageAdapterBenchmark;
use Laminas\Cache\Storage\Adapter\Memory;

/**
 * @Revs(100)
 * @Iterations(10)
 * @Warmup(1)
 */
class MemoryStorageAdapterBench extends AbstractStorageAdapterBenchmark
{
    public function __construct()
    {
        parent::__construct(new Memory());
    }
}
