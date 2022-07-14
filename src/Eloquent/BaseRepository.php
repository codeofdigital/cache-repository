<?php

namespace CodeOfDigital\CacheRepository\Eloquent;

use Closure;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityCreated;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityDeleted;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityUpdated;
use CodeOfDigital\CacheRepository\Exceptions\RepositoryException;
use CodeOfDigital\CacheRepository\Contracts\RepositoryInterface;
use Illuminate\Container\Container as Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Application
     */
    protected Application $app;

    /**
     * Base model according to model set in repository
     *
     * @var Model|Builder
     */
    protected Model|Builder $model;

    /**
     * Query set within the scope
     *
     * @var Closure|null
     */
    protected ?Closure $scopeQuery;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->makeModel();
        $this->boot();
    }

    public function boot()
    {
        //
    }

    abstract public function model();

    public function getModel(): Model
    {
        return $this->model;
    }

    public function resetModel()
    {
        $this->makeModel();
    }

    public function makeModel(): Model
    {
        $model = $this->app->make($this->model());
        if (!$model instanceof Model) throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        return $this->model = $model;
    }

    public function pluck($column, $key = null): mixed
    {
        $this->applyScope();
        
        $results = $this->model->pluck($column, $key);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    public function sync($id, $relation, $attributes, $detaching = true): mixed
    {
        return $this->find($id)->{$relation}()->sync($attributes, $detaching);
    }

    public function syncWithoutDetaching($id, $relation, $attributes): mixed
    {
        return $this->sync($id, $relation, $attributes, false);
    }

    public function all($columns = ['*']): mixed
    {
        $this->applyScope();

        $results = $this->model->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    public function count(array $where = [], $columns = '*'): int
    {
        $this->applyScope();

        if ($where) $this->applyConditions($where);

        $result = $this->model->count($columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    public function get($columns = ['*']): mixed
    {
        return $this->all($columns);
    }

    public function first($columns = ['*']): mixed
    {
        $this->applyScope();

        $result = $this->model->first($columns);

        $this->resetModel();

        return $result;
    }

    public function firstOrNew(array $attributes = []): mixed
    {
        $this->applyScope();

        $model = $this->model->firstOrNew($attributes);

        $this->resetModel();

        return $model;
    }

    public function firstOrCreate(array $attributes = []): mixed
    {
        $this->applyScope();

        $model = $this->model->firstOrCreate($attributes);

        $this->resetModel();

        return $model;
    }

    public function limit($limit, $columns = ['*']): mixed
    {
        $this->take($limit);
        return $this->all($columns);
    }

    public function paginate($limit = null, $columns = ['*'], $method = "paginate"): mixed
    {
        $this->applyScope();

        $limit = is_null($limit) ? Config::get('repository.pagination.limit', 15) : $limit;

        $results = $this->model->{$method}($limit, $columns);

        $results->appends(app('request')->query());

        $this->resetModel();

        return $results;
    }

    public function simplePaginate($limit = null, $columns = ['*']): mixed
    {
        return $this->paginate($limit, $columns, "simplePaginate");
    }

    public function find(int $id, $columns = ['*']): mixed
    {
        $this->applyScope();
        $model = $this->model->findOrFail($id, $columns);
        $this->resetModel();

        return $model;
    }

    public function findByField(string $field, $value = null, $columns = ['*']): Collection|array
    {
        $this->applyScope();
        $model = $this->model->where($field, '=', $value)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhere(array $where, $columns = ['*']): Collection|array
    {
        $this->applyScope();
        $this->applyConditions($where);
        $model = $this->model->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereIn($field, array $values, $columns = ['*']): Collection|array
    {
        $this->applyScope();
        $model = $this->model->whereIn($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereNotIn($field, array $values, $columns = ['*']): Collection|array
    {
        $this->applyScope();
        $model = $this->model->whereNotIn($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereBetween($field, array $values, $columns = ['*']): Collection|array
    {
        $this->applyScope();
        $model = $this->model->whereBetween($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function create(array $attributes): mixed
    {
        $model = $this->model->newInstance($attributes);
        $model->save();
        $this->resetModel();

        event(new RepositoryEntityCreated($this, $model));

        return $model;
    }

    public function update(array $attributes, int $id): mixed
    {
        $this->applyScope();

        $model = $this->model->findOrFail($id);

        $model->fill($attributes);
        $model->save();
        $this->resetModel();

        event(new RepositoryEntityUpdated($this, $model));

        return $model;
    }

    public function updateOrCreate(array $attributes, array $values = []): mixed
    {
        $this->applyScope();

        $model = $this->model->updateOrCreate($attributes, $values);
        $this->resetModel();

        event(new RepositoryEntityUpdated($this, $model));

        return $model;
    }

    public function delete(int $id): mixed
    {
        return $this->manageDeletes($id, 'delete');
    }

    public function deleteWhere(array $where): ?bool
    {
        $this->applyScope();
        $this->applyConditions($where);

        $deleted = $this->model->delete();

        event(new RepositoryEntityDeleted($this, $this->model->getModel()));

        $this->resetModel();

        return $deleted;
    }

    public function forceDelete(int $id): mixed
    {
        return $this->manageDeletes($id, 'forceDelete');
    }

    public function restore(int $id): mixed
    {
        return $this->manageDeletes($id, 'restore');
    }

    public function has($relation): static
    {
        return tap($this, function () use ($relation) {
            $this->model = $this->model->has($relation);
        });
    }

    public function with($relations): static
    {
        return tap($this, function () use ($relations) {
            $this->model = $this->model->with($relations);
        });
    }

    public function withCount($relations): static
    {
        return tap($this, function () use ($relations) {
            $this->model = $this->model->withCount($relations);
        });
    }

    public function whereHas(string $relation, Closure $closure): static
    {
        return tap($this, function () use ($relation, $closure) {
            $this->model = $this->model->whereHas($relation, $closure);
        });
    }

    public function hidden(array $fields): static
    {
        return tap($this, function () use ($fields) {
            $this->model->setHidden($fields);
        });
    }

    public function visible(array $fields): static
    {
        return tap($this, function () use ($fields) {
            $this->model->setVisible($fields);
        });
    }

    public function orderBy($column, $direction = 'asc'): static
    {
        return tap($this, function () use ($column, $direction) {
            $this->model = $this->model->orderBy($column, $direction);
        });
    }

    public function take($limit): static
    {
        return tap($this, function () use ($limit) {
            $this->model = $this->model->limit($limit);
        });
    }

    public function scopeQuery(Closure $scope): static
    {
        return tap($this, function () use ($scope) {
            $this->scopeQuery = $scope;
        });
    }

    public function resetScope(): static
    {
        return tap($this, function () {
            $this->scopeQuery = null;
        });
    }

    public function applyScope(): static
    {
        return tap($this, function () {
            if (isset($this->scopeQuery) && is_callable($this->scopeQuery)) {
                $callback = $this->scopeQuery;
                $this->model = $callback($this->model);
            }
        });
    }

    protected function manageDeletes(int $id, string $method)
    {
        $this->applyScope();

        if (($method === 'forceDelete' || $method === 'restore') && !in_array(SoftDeletes::class, class_uses($this->model)))
            throw new RepositoryException("Model must implement SoftDeletes Trait to use forceDelete() or restore() method.");

        $model = $this->model->withTrashed()->find($id);
        $originalModel = clone $model;

        $this->resetModel();

        $model->{$method}();

        if ($method === 'restore') event(new RepositoryEntityUpdated($this, $originalModel));
        else event(new RepositoryEntityDeleted($this, $originalModel));

        return $model;
    }

    protected function applyConditions(array $where)
    {
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($field, $condition, $val) = $value;
                $condition = preg_replace('/\s\s+/', ' ', trim($condition));

                $operator = explode(' ', $condition);

                if (count($operator) > 1) {
                    $condition = $operator[0];
                    $operator = $operator[1];
                } else $operator = null;

                switch (strtoupper($condition)) {
                    case 'IN':
                        if (!is_array($val)) throw new RepositoryException("Input {$val} mus be an array");
                        $this->model = $this->model->whereIn($field, $val);
                        break;
                    case 'NOTIN':
                        if (!is_array($val)) throw new RepositoryException("Input {$val} mus be an array");
                        $this->model = $this->model->whereNotIn($field, $val);
                        break;
                    case 'DATE':
                        if (!$operator) $operator = '=';
                        $this->model = $this->model->whereDate($field, $operator, $val);
                        break;
                    case 'DAY':
                        if (!$operator) $operator = '=';
                        $this->model = $this->model->whereDay($field, $operator, $val);
                        break;
                    case 'MONTH':
                        if (!$operator) $operator = '=';
                        $this->model = $this->model->whereMonth($field, $operator, $val);
                        break;
                    case 'YEAR':
                        if (!$operator) $operator = '=';
                        $this->model = $this->model->whereYear($field, $operator, $val);
                        break;
                    case 'EXISTS':
                        if (!($val instanceof Closure)) throw new RepositoryException("Input {$val} must be closure function");
                        $this->model = $this->model->whereExists($val);
                        break;
                    case 'HAS':
                        if (!($val instanceof Closure)) throw new RepositoryException("Input {$val} must be closure function");
                        $this->model = $this->model->whereHas($field, $val);
                        break;
                    case 'HASMORPH':
                        if (!($val instanceof Closure)) throw new RepositoryException("Input {$val} must be closure function");
                        $this->model = $this->model->whereHasMorph($field, $val);
                        break;
                    case 'DOESNTHAVE':
                        if (!($val instanceof Closure)) throw new RepositoryException("Input {$val} must be closure function");
                        $this->model = $this->model->whereDoesntHave($field, $val);
                        break;
                    case 'DOESNTHAVEMORPH':
                        if (!($val instanceof Closure)) throw new RepositoryException("Input {$val} must be closure function");
                        $this->model = $this->model->whereDoesntHaveMorph($field, $val);
                        break;
                    case 'BETWEEN':
                        if (!is_array($val)) throw new RepositoryException("Input {$val} mus be an array");
                        $this->model = $this->model->whereBetween($field, $val);
                        break;
                    case 'BETWEENCOLUMNS':
                        if (!is_array($val)) throw new RepositoryException("Input {$val} mus be an array");
                        $this->model = $this->model->whereBetweenColumns($field, $val);
                        break;
                    case 'NOTBETWEEN':
                        if (!is_array($val)) throw new RepositoryException("Input {$val} mus be an array");
                        $this->model = $this->model->whereNotBetween($field, $val);
                        break;
                    case 'NOTBETWEENCOLUMNS':
                        if (!is_array($val)) throw new RepositoryException("Input {$val} mus be an array");
                        $this->model = $this->model->whereNotBetweenColumns($field, $val);
                        break;
                    case 'RAW':
                        $this->model = $this->model->whereRaw($val);
                        break;
                    default:
                        $this->model = $this->model->where($field, $condition, $val);
                }
            } else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    public static function __callStatic($method, $arguments)
    {
        return call_user_func_array([new static(), $method], $arguments);
    }

    public function __call($method, $arguments)
    {
        $this->applyScope();
        return call_user_func_array([$this->model, $method], $arguments);
    }
}