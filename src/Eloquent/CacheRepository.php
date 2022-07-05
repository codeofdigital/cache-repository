<?php

namespace CodeOfDigital\CacheRepository\Eloquent;

use CodeOfDigital\CacheRepository\Contracts\CacheableInterface;
use CodeOfDigital\CacheRepository\Traits\Cacheable;
use Illuminate\Container\Container as Application;

abstract class CacheRepository extends BaseRepository implements CacheableInterface
{
    use Cacheable;

    public function __construct(Application $app)
    {
        $this->cacheRepository = $this->getCacheRepository();
        $this->cacheMinutes = $this->getCacheTTL();
        parent::__construct($app);
    }
}