<?php

namespace CodeOfDigital\CacheRepository\Events;

class RepositoryEntityUpdated extends RepositoryEventBase
{
    protected string $action = "updated";
}
