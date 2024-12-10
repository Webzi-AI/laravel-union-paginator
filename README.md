
# UnionPaginator Documentation

## Introduction

The `UnionPaginator` package enables you to paginate and unify results from multiple Eloquent models into a single dataset. By merging multiple model queries, it allows for straightforward pagination and sorting of data drawn from various sources.

**Key Features:**
- Unite and paginate multiple Eloquent model results in one go.
- Apply per-model filters (scopes) before the union.
- Choose between retrieving actual Eloquent models or working with raw database records.
- Mitigate N+1 queries by loading models in bulk.
- Customize selected columns for each model type.

## Installation

Install via Composer:

```bash
composer require austinw/union-paginator
```

## Migration Guide

If you are upgrading from an earlier version of `UnionPaginator`, please refer to the [Migration Guide](MIGRATION.md) for detailed instructions on updating your code to take advantage of the latest features and improvements.

## Getting Started

### Initializing the UnionPaginator

Specify which Eloquent models you want to combine:

```php
use AustinW\UnionPaginator\UnionPaginator;

$paginator = UnionPaginator::forModels([User::class, Post::class]);
```

All provided classes must be subclasses of `Illuminate\Database\Eloquent\Model`.

### Paginating Data

Call `paginate` to get paginated results:

```php
$results = $paginator->paginate(15);
```

This returns a `LengthAwarePaginator` instance, seamlessly integrating with Laravel’s pagination utilities.

### Applying Scopes to Individual Models

You can apply specific query conditions to a single model type before creating the union:

```php
$paginator->applyScope(User::class, fn($query) => $query->where('active', true));
```

Now only active users are included in the final union.

### Transforming Results

Use `transformResultsFor` to alter records for a particular model type:

```php
$paginator->transformResultsFor(User::class, fn($user) => [
    'id' => $user->id,
    'uppercase_name' => strtoupper($user->name),
]);
```

If model retrieval is active, `$user` is an Eloquent model. If you call `preventModelRetrieval()`, `$user` is a raw database record (`stdClass`).

### Preventing Model Retrieval

If you don’t need Eloquent models and prefer raw records:

```php
$paginator->preventModelRetrieval()->paginate();
```

Transformations still apply, but are run on raw records.

### Selecting Columns

Choose specific columns for each model type to reduce overhead:

```php
$paginator->setSelectedColumns(User::class, ['id', 'email', DB::raw("'User' as type")]);
```

### Soft Deletes

Models using `SoftDeletes` are automatically filtered so that soft-deleted records do not appear.

## Methods

- **forModels(array $modelTypes): self**  
  Set the models to combine. Throws an exception if a non-model class is provided.

- **applyScope(string \$modelType, Closure $callable): self**  
  Modify queries for an individual model type.

- **transformResultsFor(string \$modelType, Closure $callable): self**  
  Apply transformations to either models or raw records of a particular model type.

- **preventModelRetrieval(): self**  
  Skip loading actual models. Return raw database rows instead.

- **setSelectedColumns(string \$modelType, array $columns): self**  
  Specify which columns to fetch for each model type.

- **paginate(\$perPage = 15, \$columns = ['*'], \$pageName = 'page', $page = null): LengthAwarePaginator**  
  Execute the union query, apply scopes and transformations, and return a paginator.

- **__call(\$method, $parameters)**  
  Forward method calls to the underlying union query builder, enabling sorting and other query modifications.

## Example Usage

```php
use AustinW\UnionPaginator\UnionPaginator;

$paginator = UnionPaginator::forModels([User::class, Post::class])
    ->applyScope(User::class, fn($query) => $query->where('active', true))
    ->transformResultsFor(User::class, fn($user) => ['id' => $user->id, 'name' => strtoupper($user->name)])
    ->transformResultsFor(Post::class, fn($post) => ['title' => $post->title, 'date' => $post->created_at->toDateString()])
    ->paginate(10);

foreach ($paginator->items() as $item) {
    // Each $item could be a transformed array or a raw record, depending on your configuration.
}
```

## Advanced Usage

### Ordering and Complex Queries

You can chain Eloquent methods before `paginate()`:

```php
$paginator->latest()->paginate();
```

or

```php
$paginator->orderBy('created_at', 'desc')->paginate();
```

### Multiple Transformations for the Same Model

Applying multiple transformations for the same model type overwrites earlier ones:

```php
$paginator->transformResultsFor(User::class, fn($user) => ['transformed' => true])
          ->transformResultsFor(User::class, fn($user) => ['overridden' => true]);
```

The latter transformation takes precedence.

### Handling Empty Results

If no matching records are found, the paginator returns an empty result set without errors.

## Testing

`UnionPaginator` is well-tested across various scenarios, including:
- Multiple model unions.
- Soft deletes handling.
- Both raw and model-based transformations.
- Large datasets and edge cases.

## Conclusion

`UnionPaginator` simplifies managing unified pagination across multiple Eloquent models. With features like scope application, bulk model loading, customizable transformations, and raw record retrieval, it offers a flexible and efficient tool that fits smoothly into Laravel’s ecosystem.
