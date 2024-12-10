<?php

namespace AustinW\UnionPaginator;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class UnionPaginator
{
    use ForwardsCalls;

    protected array $modelTypes = [];

    public Builder $query;

    public ?EloquentBuilder $unionQuery = null;

    public array $scopes = [];

    public array $transformers = [];

    protected array $selectedColumns = [];

    protected bool $preventModelRetrieval = false;

    public function __construct(array|string $modelTypes = [])
    {
        foreach (Arr::wrap($modelTypes) as $modelType) {
            if (!is_subclass_of($modelType, Model::class)) {
                throw new InvalidArgumentException("$modelType is not a subclass of " . Model::class);
            }

            $this->addModelType($modelType);
        }
    }

    public static function forModels(array $modelTypes): self
    {
        return new self($modelTypes);
    }

    public function addModelType(string $modelType): self
    {
        $this->modelTypes[] = $modelType;
        return $this;
    }

    public function preventModelRetrieval(): self
    {
        $this->preventModelRetrieval = true;
        return $this;
    }

    public function prepareUnionQuery(): self
    {
        $this->unionQuery = null;

        if (empty($this->modelTypes)) {
            throw new BadMethodCallException('No models have been added to the UnionPaginator.');
        }

        foreach ($this->modelTypes as $modelType) {
            /** @var Model $model */
            $model = new $modelType;
            $columns = $this->selectedColumns[$modelType] ?? $this->defaultColumns($model);

            $query = $model->newQuery()->select($columns);

            if ($this->hasScope($modelType)) {
                foreach ($this->addFilterFor($modelType) as $modelScope) {
                    $modelScope($query);
                }
            }

            if ($this->unionQuery) {
                $this->unionQuery = $this->unionQuery->union($query);
            } else {
                $this->unionQuery = $query;
            }
        }

        return $this;
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        if (!$this->unionQuery) {
            $this->prepareUnionQuery();
        }

        // Paginate the union query
        $paginated = DB::table(DB::raw("({$this->unionQuery->toSql()}) as subquery"))
            ->mergeBindings($this->unionQuery->getQuery())
            ->paginate($perPage, $columns, $pageName, $page);

        $items = $paginated->items();

        // If there are no items, just return as-is
        if (empty($items)) {
            return $paginated;
        }

        if ($this->preventModelRetrieval) {
            // Return raw records, optionally apply transformations on the raw record
            $transformedItems = [];
            foreach ($items as $item) {
                $modelType = $item->type;
                if (isset($this->transformers[$modelType])) {
                    $callable = $this->transformers[$modelType];
                    $transformedItems[] = $callable($item);
                } else {
                    $transformedItems[] = $item;
                }
            }

            $paginated->setCollection(collect($transformedItems));
            return $paginated;
        }

        // If model retrieval is not prevented, do the mass retrieval optimization
        $itemsByType = collect($items)->groupBy('type');
        $modelsByType = [];

        foreach ($itemsByType as $modelType => $groupedItems) {
            /** @var Model $modelType */
            $ids = collect($groupedItems)->pluck('id')->unique()->toArray();
            $models = $modelType::findMany($ids);
            $modelsByType[$modelType] = $models->keyBy($models->first()?->getKeyName() ?? 'id');
        }

        $transformedItems = [];
        foreach ($items as $item) {
            $modelType = $item->type;
            $id = $item->id;
            $loadedModel = $modelsByType[$modelType][$id] ?? null;

            if (isset($this->transformers[$modelType])) {
                $callable = $this->transformers[$modelType];
                $transformedItems[] = $callable($loadedModel);
            } else {
                $transformedItems[] = $loadedModel;
            }
        }

        $paginated->setCollection(collect($transformedItems));

        return $paginated;
    }

    public function transformResultsFor(string $modelType, Closure $callable): self
    {
        if (!in_array($modelType, $this->modelTypes)) {
            return $this;
        }

        $this->transformers[$modelType] = $callable;

        return $this;
    }

    public function hasScope(string $modelType): bool
    {
        return collect($this->scopes)->filter(fn ($scope) => $scope[0] === $modelType)->isNotEmpty();
    }

    public function addFilterFor(string $modelType): Collection
    {
        return collect($this->scopes)->filter(fn ($scope) => $scope[0] === $modelType)->map(fn ($scope) => $scope[1]);
    }

    public function applyScope(string $modelType, Closure $callable): self
    {
        $this->scopes[] = [$modelType, $callable];
        return $this;
    }

    public function getModelTypes(): array
    {
        return $this->modelTypes;
    }

    public function setModelTypes(array $modelTypes): self
    {
        $this->modelTypes = $modelTypes;
        return $this;
    }

    public function setSelectedColumns(string $modelType, array $columns): self
    {
        $this->selectedColumns[$modelType] = $columns;
        return $this;
    }

    protected function defaultColumns(Model $model): array
    {
        return [
            $model->getKeyName(),
            'created_at',
            'updated_at',
            DB::raw(sprintf("'%s' as type", $model::class))
        ];
    }

    public function __call($method, $parameters)
    {
        if (!$this->unionQuery) {
            $this->prepareUnionQuery();
        }

        $this->forwardCallTo($this->unionQuery, $method, $parameters);

        return $this;
    }
}
