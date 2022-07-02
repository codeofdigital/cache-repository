<?php

namespace CodeOfDigital\CacheRepository\Events;

class RepositoryEntityDeleted extends RepositoryEventBase
{
    protected string $action = "deleted";
}
