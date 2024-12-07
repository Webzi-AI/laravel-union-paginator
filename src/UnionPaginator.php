<?php

namespace AustinW\UnionPaginator;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UnionPaginator
{
    use ForwardsCalls;

    public Builder $query;

    public ?Builder $unionQuery = null;

    public array $through = [];

    public function __construct(protected array $modelTypes = [])
    {
        $this->unionQuery = null;

        foreach ($this->modelTypes as $modelType) {
            if (!is_subclass_of($modelType, Model::class)) {
                throw new BadMethodCallException("{$modelType} is not a subclass of " . Model::class);
            }

            /** @var Model $model */
            $model = new $modelType;
            $baseClass = basename($modelType);

            $query = DB::table($model->getTable())
                ->select('id', 'created_at', DB::raw("'{$baseClass}' as type"));

            if (in_array(SoftDeletes::class, class_uses($model))) {
                $query->whereNull('deleted_at');
            }

            if ($this->unionQuery) {
                $this->unionQuery = $this->unionQuery->union($query);
            } else {
                $this->unionQuery = $query;
            }
        }
    }

    public static function for(array $modelTypes): self
    {
        return new self($modelTypes);
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        if (!$this->unionQuery) {
            throw new BadMethodCallException('No union query has been created.');
        }

        $paginated = DB::table(DB::raw("({$this->unionQuery->toSql()}) as subquery"))
            ->mergeBindings($this->unionQuery)
            ->paginate($perPage, $columns, $pageName, $page);

        $paginated->through(function ($record) {
            if (Arr::has($this->through, $record->type)) {
                /** @var Closure $callable */
                $callable = $this->through[$record->type];

                return $callable($record);
            }

            return $record->type::find($record->id);
        });

        return $paginated;
    }

    public function transform(string $modelType, Closure $callable): self
    {
        if (!in_array($modelType, $this->modelTypes)) {
            return $this;
        }

        $this->through[$modelType] = $callable;

        return $this;
    }

    public function getModelTypes(): array
    {
        return $this->modelTypes;
    }

    public function setModelTypes(array $modelTypes): UnionPaginator
    {
        $this->modelTypes = $modelTypes;
        return $this;
    }

    public function __call($method, $parameters)
    {
        if ($this->unionQuery) {
            // Forward method calls to the unionQuery instance
            $this->forwardCallTo($this->unionQuery, $method, $parameters);

            return $this;
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }
}
