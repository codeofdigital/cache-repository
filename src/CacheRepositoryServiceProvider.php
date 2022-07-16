<?php

namespace CodeOfDigital\CacheRepository;

use CodeOfDigital\CacheRepository\Commands\CriteriaCommand;
use CodeOfDigital\CacheRepository\Commands\RepositoryCommand;
use Illuminate\Support\ServiceProvider;

class CacheRepositoryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishAssets();
        $this->mergeConfigFrom(__DIR__.'/../config/repository.php', 'repository');
    }

    protected function publishAssets()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/repository.php' => base_path('config/repository.php')]);
        }
    }

    public function register()
    {
        $this->app->register(EventServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RepositoryCommand::class]);
            $this->commands([CriteriaCommand::class]);
        }
    }
}