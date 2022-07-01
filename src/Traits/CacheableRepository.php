<?php

namespace CodeOfDigital\CacheRepository\Traits;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Config;

trait CacheableRepository
{
    protected CacheRepository $cacheRepository;

    public function setCacheRepository(CacheRepository $repository): static
    {
        $this->cacheRepository = $repository;
        return $this;
    }

    public function getCacheRepository()
    {
        if (is_null($this->cacheRepository))
            $this->cacheRepository = app(Config::get('repository.cache.repository', 'cache'));

        return $this->cacheRepository;
    }

    public function skipCache($status = true): static
    {
        $this->cacheSkip = $status;
        return $this;
    }

    public function isSkippedCache(): bool
    {
        $skipped = $this->cacheSkip ?? false;
        $request = app('Illuminate\Http\Request');
        $skipCacheParam = config('repository.cache.params.skipCache', 'skipCache');

        if ($request->has($skipCacheParam) && $request->get($skipCacheParam)) $skipped = true;

        return $skipped;
    }

    protected function allowedCache($method): bool
    {
        $cacheEnabled = Config::get('repository.cache.enabled', true);

        if (!$cacheEnabled) return false;

        $cacheOnly = $this->cacheOnly ?? config('repository.cache.allowed.only', null);
        $cacheExcept = $this->cacheExcept ?? config('repository.cache.allowed.except', null);

        if (is_array($cacheOnly)) return in_array($method, $cacheOnly);

        if (is_array($cacheExcept)) return !in_array($method, $cacheExcept);

        if (is_null($cacheOnly) && is_null($cacheExcept)) return true;

        return false;
    }

    public function getCacheKey($method, $args = null)
    {
        $request = app('Illuminate\Http\Request');
        $args = serialize($args);
        $key = sprintf('%s@%s-%s', get_called_class(), $method, md5($args . $request->fullUrl()));

        return $key;
    }
}