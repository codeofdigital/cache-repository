<?php

namespace CodeOfDigital\CacheRepository\Contracts;

use Closure;

interface RepositoryInterface
{
    public function pluck($column, $key = null);

    public function sync($id, $relation, $attributes, $detaching = true);

    public function syncWithoutDetaching($id, $relation, $attributes);

    public function all($columns = ['*']);

    public function paginate($limit = null, $columns = ['*']);

    public function simplePaginate($limit = null, $columns = ['*']);

    public function find($id, $columns = ['*']);

    public function findByField($field, $value, $columns = ['*']);

    public function findWhere(array $where, $columns = ['*']);

    public function findWhereIn($field, array $values, $columns = ['*']);

    public function findWhereNotIn($field, array $values, $columns = ['*']);

    public function findWhereBetween($field, array $values, $columns = ['*']);

    public function create(array $attributes);

    public function update(array $attributes, $id);

    public function updateOrCreate(array $attributes, array $values = []);

    public function delete(int $id);

    public function orderBy($column, $direction = 'asc');

    public function with($relations);

    public function whereHas(string $relation, Closure $closure);

    public function withCount($relations);

    public function hidden(array $fields);

    public function visible(array $fields);

    public function scopeQuery(Closure $scope);

    public function resetScope();

    public function firstOrNew(array $attributes = []);

    public function firstOrCreate(array $attributes = []);

    public static function __callStatic($method, $arguments);

    public function __call($method, $arguments);
}