<?php

namespace CodeOfDigital\CacheRepository\Traits;

use CodeOfDigital\CacheRepository\Helpers\CacheKeys;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Config;

trait CacheableRepository
{
    protected CacheRepository $cacheRepository;

    protected bool $cacheSkip;

    public function setCacheRepository(CacheRepository $repository): static
    {
        $this->cacheRepository = $repository;
        return $this;
    }

    public function getCacheRepository(): CacheRepository
    {
        return $this->cacheRepository;
    }

    public function skipCache($status = true)
    {
        return tap($this, function () use ($status) {
            $this->cacheSkip = $status;
        });
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

    public function getCacheKey($method, $args = null): string
    {
        $request = app('Illuminate\Http\Request');
        $args = serialize($args);
        $repositoryClass = get_called_class();

        $key = sprintf('%s@%s-%s', $repositoryClass, $method, md5($args . $request->fullUrl()));

        CacheKeys::putKey($repositoryClass, $key);

        return $key;
    }

    public function getCacheTTL(): float|int
    {
        $cacheMinutes = $this->cacheMinutes ?? config('repository.cache.minutes', 30);
        return $cacheMinutes * 60;
    }

    public function all($columns = ['*'])
    {
        if (!$this->allowedCache('all') || $this->isSkippedCache())
            return parent::all($columns);

        $key = $this->getCacheKey('all', func_get_args());
        $time = $this->getCacheTTL();
        $value = $this->getCacheRepository()->remember($key, $time, function () use ($columns) {
            return parent::all($columns);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }

    public function paginate($limit = null, $columns = ['*'], $method = 'paginate')
    {
        if (!$this->allowedCache('paginate') || $this->isSkippedCache()) {
            return parent::paginate($limit, $columns, $method);
        }

        $key = $this->getCacheKey('paginate', func_get_args());
        $time = $this->getCacheTTL();
        $value = $this->getCacheRepository()->remember($key, $time, function () use ($limit, $columns, $method) {
            return parent::paginate($limit, $columns, $method);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }
}