# UnionPaginator Documentation

## Introduction

The `UnionPaginator` package provides a powerful and flexible way to paginate across multiple Eloquent models in Laravel. By uniting queries from different models into a single dataset, it allows you to paginate and sort data from multiple sources seamlessly.

## Installation

Install the package via Composer:

```bash
composer require austinw/union-paginator
```

## Getting Started

### Creating a UnionPaginator Instance

The `UnionPaginator` is initialized with an array of model classes that you want to paginate. These models must extend `Illuminate\Database\Eloquent\Model`.

```php
use AustinW\UnionPaginator\UnionPaginator;

$paginator = new UnionPaginator([User::class, Post::class]);
```

Alternatively, use the static `for` method:

```php
$paginator = UnionPaginator::for([User::class, Post::class]);
```

### Paginating Results

To paginate the unified results, use the `paginate` method:

```php
$result = $paginator->paginate(15); // Paginate with 15 items per page
```

This returns a `LengthAwarePaginator` instance.

### Transforming Records

You can apply transformations to records of specific models using the `transform` method. This is useful for customizing the output format of certain models.

```php
$paginator->transform(User::class, function ($record) {
    return [
        'id' => $record->id,
        'type' => 'user',
    ];
});
```

### Soft Deletes

If any of the models use the `SoftDeletes` trait, `UnionPaginator` automatically excludes soft-deleted records from the results.

## Methods

### `__construct(array $modelTypes)`

Initializes the paginator with an array of model classes.

- **Parameters:**
    - `array $modelTypes`: List of model class names to include in the union query.
- **Exceptions:**
    - Throws `BadMethodCallException` if any class in the array does not extend `Model`.

### `paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)`

Paginates the results of the union query.

- **Parameters:**
    - `$perPage`: Number of results per page (default: `15`).
    - `$columns`: Columns to select (default: `['*']`).
    - `$pageName`: Name of the query string parameter for the page (default: `'page'`).
    - `$page`: Current page number (optional).
- **Returns:** A `LengthAwarePaginator` instance.

### `transform(string $modelType, Closure $callable): self`

Applies a transformation to the records of a specific model type.

- **Parameters:**
    - `$modelType`: The model class name to transform.
    - `$callable`: A `Closure` that accepts a record and returns the transformed value.
- **Returns:** The `UnionPaginator` instance for method chaining.

### `getModelTypes(): array`

Retrieves the list of model types used in the union query.

- **Returns:** An array of model class names.

### `setModelTypes(array $modelTypes): self`

Updates the list of model types for the paginator.

- **Parameters:**
    - `$modelTypes`: The new list of model types.
- **Returns:** The `UnionPaginator` instance for method chaining.

### `__call($method, $parameters)`

Forwards method calls to the underlying union query, allowing dynamic query customization.

- **Parameters:**
    - `$method`: The method name to call on the union query.
    - `$parameters`: The parameters for the method.
- **Throws:** `BadMethodCallException` if no union query is defined.

## Advanced Features

### Handling Complex Unions

The `UnionPaginator` ensures proper binding of parameters in complex union queries, maintaining database compatibility and performance.

### Ordering Results

By default, results are ordered by `created_at` in descending order. Use query methods like `orderBy` or `latest` to customize sorting:

```php
$paginator->latest()->paginate();
```

### Combining Transformations

Apply multiple transformations to the same model type. The latest transformation overrides previous ones:

```php
$paginator->transform(User::class, fn($record) => ['transformed' => true])
          ->transform(User::class, fn($record) => ['overridden' => true]);
```

### Handling Empty Result Sets

The paginator gracefully handles empty results, returning a paginator with zero total items.

## Testing

The package includes extensive test coverage, ensuring reliability in various scenarios, such as:

- Pagination with large datasets.
- Handling of soft-deleted records.
- Transforming and overriding transformations.
- Boundary conditions and invalid input.

## Example Usage

```php
use AustinW\UnionPaginator\UnionPaginator;

// Define the models
$paginator = UnionPaginator::for([User::class, Post::class]);

// Apply transformations
$paginator->transform(User::class, fn($record) => [
    'id' => $record->id,
    'name' => $record->name,
])->transform(Post::class, fn($record) => [
    'title' => $record->title,
    'created_at' => $record->created_at,
]);

// Paginate the results
$paginatedResults = $paginator->paginate(10);

// Iterate through paginated items
foreach ($paginatedResults->items() as $item) {
    // Process the paginated item
}
```

## Conclusion

`UnionPaginator` is a robust solution for unified pagination across multiple models, combining simplicity with extensibility. Its seamless integration with Laravel’s query builder and pagination makes it a must-have for complex applications.
