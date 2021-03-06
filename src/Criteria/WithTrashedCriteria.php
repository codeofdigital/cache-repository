<?php

namespace CodeOfDigital\CacheRepository\Criteria;

use CodeOfDigital\CacheRepository\Contracts\CriteriaInterface;
use CodeOfDigital\CacheRepository\Contracts\RepositoryInterface;
use CodeOfDigital\CacheRepository\Exceptions\RepositoryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WithTrashedCriteria implements CriteriaInterface
{
    /**
     * Apply conditions into current query building
     *
     * @param Model|Builder $model
     * @param RepositoryInterface $repository
     * @return mixed
     * @throws RepositoryException
     */
    public function apply(Model|Builder $model, RepositoryInterface $repository): mixed
    {
        $checkModel = $model instanceof Builder ? $model->getModel() : $model;

        if (!in_array(SoftDeletes::class, class_uses($checkModel)))
            throw new RepositoryException('Model must implement SoftDeletes Trait to use this criteria.');

        return $model->withTrashed();
    }
}