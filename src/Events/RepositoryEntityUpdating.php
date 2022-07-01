<?php

namespace CodeOfDigital\CacheRepository\Events;

/**
 * Class RepositoryEntityUpdated
 * @package Prettus\Repository\Events
 * @author Anderson Andrade <contato@andersonandra.de>
 */
class RepositoryEntityUpdating extends RepositoryEventBase
{
    protected string $action = "updating";
}
