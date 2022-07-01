<?php

namespace CodeOfDigital\CacheRepository\Events;

use Illuminate\Database\Eloquent\Model;
use CodeOfDigital\CacheRepository\Contracts\RepositoryInterface;

abstract class RepositoryEventBase
{
    protected Model $model;

    protected RepositoryInterface $repository;

    protected string $action;

    public function __construct(RepositoryInterface $repository, Model $model = null)
    {
        $this->repository = $repository;
        $this->model = $model;
    }

    public function getModel(): Model|array
    {
        return $this->model;
    }

    public function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
