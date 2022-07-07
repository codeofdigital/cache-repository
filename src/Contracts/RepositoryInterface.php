<?php

namespace CodeOfDigital\CacheRepository\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Collection;

interface RepositoryInterface
{
    public function pluck($column, $key = null): Collection|array;

    public function sync($id, $relation, $attributes, $detaching = true): mixed;

    public function syncWithoutDetaching($id, $relation, $attributes): mixed;

    public function all($columns = ['*']): mixed;

    public function count(array $where = [], $columns = '*'): int;

    public function get($columns = ['*']): mixed;

    public function first($columns = ['*']): mixed;

    public function firstOrNew(array $attributes = []): mixed;

    public function firstOrCreate(array $attributes = []): mixed;

    public function limit($limit, $columns = ['*']): mixed;

    public function paginate($limit = null, $columns = ['*']): mixed;

    public function simplePaginate($limit = null, $columns = ['*']): mixed;

    public function find(int $id, $columns = ['*']): mixed;

    public function findByField(string $field, $value, $columns = ['*']): Collection|array;

    public function findWhere(array $where, $columns = ['*']): Collection|array;

    public function findWhereIn($field, array $values, $columns = ['*']): Collection|array;

    public function findWhereNotIn($field, array $values, $columns = ['*']): Collection|array;

    public function findWhereBetween($field, array $values, $columns = ['*']): Collection|array;

    public function create(array $attributes): mixed;

    public function update(array $attributes, int $id): mixed;

    public function updateOrCreate(array $attributes, array $values = []): mixed;

    public function delete(int $id): mixed;

    public function deleteWhere(array $where): ?bool;

    public function forceDelete(int $id): mixed;

    public function restore(int $id): mixed;

    public function has($relation): static;

    public function with($relations): static;

    public function whereHas(string $relation, Closure $closure): static;

    public function withCount($relations): static;

    public function hidden(array $fields): static;

    public function visible(array $fields): static;

    public function orderBy($column, $direction = 'asc'): static;

    public function take($limit): static;

    public function scopeQuery(Closure $scope): static;

    public function resetScope(): static;

    public function applyScope(): static;

    public static function __callStatic($method, $arguments);

    public function __call($method, $arguments);
}
