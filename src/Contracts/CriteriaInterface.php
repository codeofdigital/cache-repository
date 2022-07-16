<?php

namespace CodeOfDigital\CacheRepository\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CriteriaInterface
{
    /**
     * Apply a query criteria in repository
     *
     * @param Model $model
     * @param RepositoryInterface $repository
     * @return mixed
     */
    public function apply(Model $model, RepositoryInterface $repository): mixed;
}