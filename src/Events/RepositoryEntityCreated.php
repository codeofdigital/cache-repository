<?php

namespace CodeOfDigital\CacheRepository\Events;

class RepositoryEntityCreated extends RepositoryEventBase
{
    /**
     * @var string
     */
    protected $action = "created";
}
