<?php

namespace CodeOfDigital\CacheRepository;

use CodeOfDigital\CacheRepository\Listeners\RepositoryEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    protected $subscribe = [
        RepositoryEventSubscriber::class
    ];
}