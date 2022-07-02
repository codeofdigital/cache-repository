<?php

namespace CodeOfDigital\CacheRepository;

use Illuminate\Support\ServiceProvider;

class CacheRepositoryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/repository.php', 'repository');
    }

    public function register()
    {
        $this->app->register(EventServiceProvider::class);
    }
}