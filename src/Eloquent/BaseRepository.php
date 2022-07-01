<?php

namespace CodeOfDigital\CacheRepository\Eloquent;

use Closure;
use CodeOfDigital\CacheRepository\Exceptions\RepositoryException;
use CodeOfDigital\CacheRepository\Contracts\RepositoryInterface;
use Illuminate\Container\Container as Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

abstract class BaseRepository implements RepositoryInterface
{
    protected Application $app;
    protected Model $model;
    protected ?Closure $scopeQuery = null;

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

    public function scopeQuery(\Closure $scope): BaseRepository
    {
        return tap($this, function () use ($scope) {
            $this->scopeQuery = $scope;
        });
    }

    public function pluck($column, $key = null)
    {
        return $this->model->pluck($column, $key);
    }

    public function sync($id, $relation, $attributes, $detaching = true)
    {
        return $this->find($id)->{$relation}()->sync($attributes, $detaching);
    }

    public function syncWithoutDetaching($id, $relation, $attributes)
    {
        return $this->sync($id, $relation, $attributes, false);
    }

    public function all($columns = ['*'])
    {
        $this->applyScope();

        if ($this->model instanceof Builder) $results = $this->model->get($columns);
        else $results = $this->model->all($columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    public function count(array $where = [], $columns = '*')
    {
        $this->applyScope();

        if ($where) $this->applyConditions($where);

        $result = $this->model->count($columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    public function get($columns = ['*']): array|Collection
    {
        return $this->all($columns);
    }

    public function first($columns = ['*'])
    {
        $this->applyScope();

        $result = $this->model->first($columns);

        $this->resetModel();

        return $result;
    }

    public function firstOrNew(array $attributes = [])
    {
        $this->applyScope();

        $model = $this->model->firstOrNew($attributes);

        $this->resetModel();

        return $model;
    }

    public function firstOrCreate(array $attributes = [])
    {
        $this->applyScope();

        $model = $this->model->firstOrCreate($attributes);

        $this->resetModel();

        return $model;
    }

    public function limit($limit, $columns = ['*']): array|Collection
    {
        $this->take($limit);
        return $this->all($columns);
    }

    public function paginate($limit = null, $columns = ['*'], $method = "paginate")
    {
        $this->applyScope();

        $limit = is_null($limit) ? Config::get('repository.pagination.limit', 15) : $limit;

        $results = $this->model->{$method}($limit, $columns);

        $results->appends(app('request')->query());

        $this->resetModel();

        return $results;
    }

    public function simplePaginate($limit = null, $columns = ['*'])
    {
        return $this->paginate($limit, $columns, "simplePaginate");
    }

    public function find($id, $columns = ['*'])
    {
        $this->applyScope();
        $model = $this->model->findOrFail($id, $columns);
        $this->resetModel();

        return $model;
    }

    public function findByField($field, $value = null, $columns = ['*'])
    {
        $this->applyScope();
        $model = $this->model->where($field, '=', $value)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhere(array $where, $columns = ['*'])
    {
        $this->applyScope();
        $this->applyConditions($where);
        $model = $this->model->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereIn($field, array $values, $columns = ['*'])
    {
        $this->applyScope();
        $model = $this->model->whereIn($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereNotIn($field, array $values, $columns = ['*'])
    {
        $this->applyScope();
        $model = $this->model->whereNotIn($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function findWhereBetween($field, array $values, $columns = ['*'])
    {
        $this->applyScope();
        $model = $this->model->whereBetween($field, $values)->get($columns);
        $this->resetModel();

        return $model;
    }

    public function has($relation): BaseRepository
    {
        return tap($this, function () use ($relation) {
            $this->model = $this->model->has($relation);
        });
    }

    public function with($relations): BaseRepository
    {
        return tap($this, function () use ($relations) {
            $this->model = $this->model->with($relations);
        });
    }

    public function withCount($relations): BaseRepository
    {
        return tap($this, function () use ($relations) {
            $this->model = $this->model->withCount($relations);
        });
    }

    public function whereHas(string $relation, Closure $closure): BaseRepository
    {
        return tap($this, function () use ($relation, $closure) {
            $this->model = $this->model->whereHas($relation, $closure);
        });
    }

    public function hidden(array $fields): BaseRepository
    {
        return tap($this, function () use ($fields) {
            $this->model->setHidden($fields);
        });
    }

    public function visible(array $fields)
    {
        return tap($this, function () use ($fields) {
            $this->model->setVisible($fields);
        });
    }

    public function orderBy($column, $direction = 'asc'): BaseRepository
    {
        return tap($this, function () use ($column, $direction) {
            $this->model = $this->model->orderBy($column, $direction);
        });
    }

    public function take($limit): BaseRepository
    {
        return tap($this, function () use ($limit) {
            $this->model = $this->model->limit($limit);
        });
    }

    public function resetScope(): BaseRepository
    {
        return tap($this, function () {
            $this->scopeQuery = null;
        });
    }

    public function applyScope(): BaseRepository
    {
        return tap($this, function () {
            if (isset($this->scopeQuery) && is_callable($this->scopeQuery)) {
                $callback = $this->scopeQuery;
                $this->model = $callback($this->model);
            }
        });
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