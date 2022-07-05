<?php

namespace CodeOfDigital\CacheRepository\Listeners;

use CodeOfDigital\CacheRepository\Contracts\RepositoryInterface;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityCreated;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityDeleted;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityUpdated;
use CodeOfDigital\CacheRepository\Events\RepositoryEventBase;
use CodeOfDigital\CacheRepository\Helpers\CacheKeys;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class RepositoryEventSubscriber
{
    protected CacheRepository $cache;

    protected RepositoryInterface $repository;

    protected Model $model;

    protected string $action = '';

    public function __construct()
    {
        $this->cache = app(Config::get('repository.cache.repository', 'cache.store'));
    }

    public function handleCleanCache(RepositoryEventBase $eventBase): void
    {
        try {
            $enabledClean = Config::get('repository.cache.clean.enabled', true);

            if ($enabledClean) {
                $this->repository = $eventBase->getRepository();
                $this->model = $eventBase->getModel();
                $this->action = $eventBase->getAction();

                if (Config::get("repository.cache.clean.on.{$this->action}", true)) {
                    $cacheKeys = CacheKeys::getKeys(get_class($this->repository));
                    foreach ($cacheKeys as $key) $this->cache->forget($key);
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function subscribe(): array
    {
        return [
            RepositoryEntityCreated::class => 'handleCleanCache',
            RepositoryEntityUpdated::class => 'handleCleanCache',
            RepositoryEntityDeleted::class => 'handleCleanCache',
        ];
    }
}