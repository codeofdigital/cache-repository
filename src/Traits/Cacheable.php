<?php

namespace CodeOfDigital\CacheRepository\Traits;

use CodeOfDigital\CacheRepository\Helpers\CacheKeys;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

trait Cacheable
{
    /**
     * Repository class for caching system
     *
     * @var CacheRepository
     */
    protected CacheRepository $cacheRepository;

    /**
     * Variable for checking whether to skip cache during querying
     *
     * @var bool
     */
    protected bool $cacheSkip = false;

    /**
     * Cache TTL (Time-To-Live)
     *
     * @var int
     */
    protected int $cacheMinutes;

    public function setCacheRepository(CacheRepository $repository): static
    {
        return tap($this, function () use ($repository) {
            $this->cacheRepository = $repository;
        });
    }

    public function getCacheRepository(): CacheRepository
    {
        if (isset($this->cacheRepository))
            return $this->cacheRepository;

        return app(Config::get('repository.cache.repository', 'cache.store'));
    }

    public function skipCache($status = true): static
    {
        return tap($this, function () use ($status) {
            $this->cacheSkip = $status;
        });
    }

    public function isSkippedCache(): bool
    {
        $skipped = $this->cacheSkip ?? false;
        $request = app('Illuminate\Http\Request');
        $skipCacheParam = Config::get('repository.cache.params.skipCache', 'skipCache');

        if ($request->has($skipCacheParam) && $request->get($skipCacheParam)) $skipped = true;

        return $skipped;
    }

    protected function allowedCache($method): bool
    {
        $cacheEnabled = Config::get('repository.cache.enabled', true);

        if (!$cacheEnabled) return false;

        $cacheOnly = $this->cacheOnly ?? Config::get('repository.cache.allowed.only');
        $cacheExcept = $this->cacheExcept ?? Config::get('repository.cache.allowed.except');

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

    public function setCacheTTL(int $ttl): static
    {
        return tap($this, function () use ($ttl) {
            $this->cacheMinutes = $ttl;
        });
    }

    public function getCacheTTL(): float|int
    {
        if (isset($this->cacheMinutes))
            return $this->cacheMinutes * 60;

        return Config::get('repository.cache.minutes', 30) * 60;
    }

    public function all($columns = ['*']): Collection|array
    {
        if (!$this->allowedCache('all') || $this->isSkippedCache())
            return parent::all($columns);

        $key = $this->getCacheKey('all', func_get_args());
        $time = $this->cacheMinutes;
        $value = $this->getCacheRepository()->remember($key, $time, function () use ($columns) {
            return parent::all($columns);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }

    public function paginate($limit = null, $columns = ['*'], $method = 'paginate'): mixed
    {
        if (!$this->allowedCache('paginate') || $this->isSkippedCache()) {
            return parent::paginate($limit, $columns, $method);
        }

        $key = $this->getCacheKey('paginate', func_get_args());
        $time = $this->cacheMinutes;
        $value = $this->getCacheRepository()->remember($key, $time, function () use ($limit, $columns, $method) {
            return parent::paginate($limit, $columns, $method);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }

    public function find(int $id, $columns = ['*']): Model|Builder
    {
        if (!$this->allowedCache('find') || $this->isSkippedCache()) {
            return parent::find($id, $columns);
        }

        $key = $this->getCacheKey('find', func_get_args());
        $minutes = $this->cacheMinutes;
        $value = $this->cacheRepository->remember($key, $minutes, function () use ($id, $columns) {
            return parent::find($id, $columns);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }

    public function findByField(string $field, $value = null, $columns = ['*']): Collection|array
    {
        if (!$this->allowedCache('findByField') || $this->isSkippedCache()) {
            return parent::findByField($field, $columns);
        }

        $key = $this->getCacheKey('findByField', func_get_args());
        $minutes = $this->cacheMinutes;
        $value = $this->cacheRepository->remember($key, $minutes, function () use ($field, $columns) {
            return parent::findByField($field, $columns);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }

    public function findWhere(array $where, $columns = ['*']): Collection|array
    {
        if (!$this->allowedCache('findWhere') || $this->isSkippedCache()) {
            return parent::findWhere($where, $columns);
        }

        $key = $this->getCacheKey('findWhere', func_get_args());
        $minutes = $this->cacheMinutes;
        $value = $this->cacheRepository->remember($key, $minutes, function () use ($where, $columns) {
            return parent::findWhere($where, $columns);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }
}