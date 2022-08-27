<?php

namespace CodeOfDigital\CacheRepository\Eloquent;

use Closure;
use CodeOfDigital\CacheRepository\Contracts\CriteriaInterface;
use CodeOfDigital\CacheRepository\Contracts\RepositoryCriteriaInterface;
use CodeOfDigital\CacheRepository\Contracts\RepositoryInterface;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityCreated;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityDeleted;
use CodeOfDigital\CacheRepository\Events\RepositoryEntityUpdated;
use CodeOfDigital\CacheRepository\Exceptions\RepositoryException;
use Illuminate\Container\Container as Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

abstract class BaseRepository implements RepositoryInterface, RepositoryCriteriaInterface
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
     * Collection of Criteria
     *
     * @var Collection
     */
    protected Collection $criteria;

    /**
     * Query set within the scope
     *
     * @var Closure|null
     */
    protected ?Closure $scopeQuery;

    /**
     * Whether to skip querying criteria
     *
     * @var bool
     */
    protected bool $skipCriteria = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->criteria = new Collection();
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
        $this->applyCriteria();
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
        $this->applyCriteria();
        $this->applyScope();

        $results = $this->model->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    public function count(array $where = [], $columns = '*'): int
    {
        $this->applyCriteria();
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
        $this->applyCriteria();
        $this->applyScope();

        $result = $this->model->first($columns);

        $this->resetModel();

        return $result;
    }

    public function firstOrNew(array $attributes = []): mixed
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->firstOrNew($attributes);

        $this->resetModel();

        return $model;
    }

    public function firstOrCreate(array $attributes = []): mixed
    {
        $this->applyCriteria();
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
        $this->applyCriteria();
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
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->findOrFail($id, $columns);
        $this->resetModel();

        return $model;
    }

    public function findByField(string $field, $value = null, $columns = ['*']): mixed
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->where($field, '=', $value)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhere(array $where, $columns = ['*']): mixed
    {
        $this->applyCriteria();
        $this->applyScope();

        $this->applyConditions($where);

        $model = $this->model->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereIn($field, array $values, $columns = ['*']): mixed
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->whereIn($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereNotIn($field, array $values, $columns = ['*']): mixed
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->whereNotIn($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereBetween($field, array $values, $columns = ['*']): mixed
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->whereBetween($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function create(array $attributes, bool $withoutEvents = false): mixed
    {
        $model = $this->model->newInstance($attributes);

        $method = $withoutEvents ? 'saveQuietly' : 'save';
        $model->{$method}();

        $this->resetModel();

        event(new RepositoryEntityCreated($this, $model));

        return $model;
    }

    public function insert(array $attributes): ?bool
    {
        $inserted = $this->model->insert($attributes);

        event(new RepositoryEntityCreated($this, $this->model->getModel()));

        $this->resetModel();

        return $inserted;
    }

    public function update(array $attributes, int $id, bool $withoutEvents = false): mixed
    {
        $this->applyScope();

        $model = $this->model->findOrFail($id);

        $model->fill($attributes);

        $method = $withoutEvents ? 'saveQuietly' : 'save';
        $model->{$method}();

        $this->resetModel();

        event(new RepositoryEntityUpdated($this, $model));

        return $model;
    }

    public function updateWhere(array $where, array $attributes): ?bool
    {
        $this->applyScope();
        $this->applyConditions($where);

        $updated = $this->model->update($attributes);

        event(new RepositoryEntityUpdated($this, $this->model->getModel()));

        $this->resetModel();

        return $updated;
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

    public function pushCriteria($criteria): static
    {
        return tap($this, function () use ($criteria) {
            if (is_string($criteria)) $criteria = new $criteria;

            if (!$criteria instanceof CriteriaInterface)
                throw new RepositoryException('Class ' . get_class($criteria) . ' must be an instance of CriteriaInterface');

            $this->criteria->push($criteria);
        });
    }

    public function popCriteria($criteria): static
    {
        return tap($this, function () use ($criteria) {
            $this->criteria = $this->criteria->reject(function ($item) use ($criteria) {
                if (is_object($item) && is_string($criteria))
                    return get_class($item) === $criteria;

                if (is_object($criteria) && is_string($item))
                    return $item === get_class($criteria);

                return get_class($item) === get_class($criteria);
            });
        });
    }

    public function getCriteria(): Collection
    {
        return $this->criteria;
    }

    public function getByCriteria(CriteriaInterface $criteria): mixed
    {
        $this->model = $criteria->apply($this->model, $this);
        $results = $this->model->get();
        $this->resetModel();

        return $results;
    }

    public function skipCriteria(bool $status = true): static
    {
        return tap($this, function () use ($status) {
            $this->skipCriteria = $status;
        });
    }

    public function resetCriteria(): static
    {
        return tap($this, function () {
            $this->criteria = new Collection();
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

    protected function applyCriteria(): static
    {
        if ($this->skipCriteria) return $this;

        $criteria = $this->getCriteria();

        if ($criteria->isNotEmpty()) {
            foreach ($criteria as $criterion) {
                if ($criterion instanceof CriteriaInterface)
                    $this->model = $criterion->apply($this->model, $this);
            }
        }

        return $this;
    }

    protected function manageDeletes(int $id, string $method)
    {
        $this->applyScope();

        if (($method === 'forceDelete' || $method === 'restore') && !in_array(SoftDeletes::class, class_uses($this->model)))
            throw new RepositoryException("Model must implement SoftDeletes Trait to use forceDelete() or restore() method.");

        if ($method === 'forceDelete' || $method === 'restore')
            $model = $this->model->withTrashed();
        else
            $model = $this->model;

        $model = $model->find($id);
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
        $this->applyCriteria();
        $this->applyScope();

        return call_user_func_array([$this->model, $method], $arguments);
    }
}