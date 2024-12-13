# Migration Guide

## Version 2.1.1 to 2.2.0

To migrate from version 2.1.1 to 2.2.1 of the `UnionPaginator`, you need to make a small adjustment due to a method name change. Here's a concise guide:

### Changes

1. **Method Name Update:**
   - The method `addFilterFor` has been renamed to `getScopesFor`. Update any usage of this method in your codebase.

   **Before:**
   ```php
   foreach ($this->addFilterFor($modelType) as $modelScope) {
       $modelScope($query);
   }
   ```

**After:**
   ```php
   foreach ($this->getScopesFor($modelType) as $modelScope) {
       $modelScope($query);
   }
   ```

2. **Update Method Definition:**
   - Change the method definition in your `UnionPaginator` class.

   **Before:**
   ```php
   public function addFilterFor(string $modelType): Collection
   ```

   **After:**
   ```php
   public function getScopesFor(string $modelType): Collection
   ```

This change is primarily a correction for better naming clarity and should be straightforward to implement.

## Version 1 to Version 2

This guide will help you update from the original `UnionPaginator` class to the newer version with improved functionality and developer experience.

## Overview of Changes

**Key improvements include:**

1. **Better naming conventions:**
    - `forModels` replaces `for` to clearly indicate that you are passing model classes.
    - `transformResultsFor` replaces `transform` to clarify that you are applying transformations for a particular model type.

2. **Model-Based Query Construction:**  
   Instead of building union queries purely from `DB::table()`, the paginator now starts from Eloquent query builders (`$model->newQuery()`), making it easier to leverage Eloquent features and ensuring consistent model loading.

3. **Bulk Loading of Models (Performance Optimization):**  
   The updated implementation retrieves models in bulk after pagination, preventing N+1 query issues when transforming or returning models. Instead of calling `Model::find($id)` for each record, the paginator uses `findMany()` to load all required models at once.

4. **Scopes and Filters per Model Type:**  
   You can now apply custom query modifications ("scopes") to each model type using `applyScope()`. This approach is more flexible and clearer than modifying the original union queries directly.

5. **Transformers, not `through` Callbacks:**  
   Previously, `transform` stored callbacks in a `$through` array. Now, they are stored in a `$transformers` array to better convey their purpose. The naming and approach now more closely follow Laravel conventions.

6. **Optional Raw Records (`preventModelRetrieval()`):**  
   By default, the paginator attempts to load Eloquent models for each record. A new `preventModelRetrieval()` method allows you to opt-out of this behavior and receive raw database records instead. Transformations can still be applied to these raw records if needed.

7. **Stricter Validations and Error Handling:**  
   Using `InvalidArgumentException` instead of `BadMethodCallException` for invalid model types clarifies the nature of the error. Attempting to paginate without model types or an established union query now produces clearer exceptions.

## Step-by-Step Upgrade Instructions

1. **Class Instantiation:**
    - **Before:**
      ```php
      $paginator = UnionPaginator::for([User::class, Post::class]);
      ```

    - **After:**
      ```php
      $paginator = UnionPaginator::forModels([User::class, Post::class]);
      ```

   This makes it explicit that you are passing model classes.

2. **Transforming Results:**
    - **Before:**
      ```php
      $paginator->transform(User::class, function ($record) {
          return ['name' => strtoupper($record->name)];
      });
      ```

    - **After:**
      ```php
      $paginator->transformResultsFor(User::class, function ($model) {
          return ['name' => strtoupper($model->name)];
      });
      ```

   The new name clarifies that the transformation is applied to the results of that model type.

3. **Apply Scopes:**
    - **New Feature (No direct equivalent previously):**
      ```php
      $paginator->applyScope(User::class, fn($query) => $query->where('active', true));
      ```

   Now, you can easily apply filters or modifications to a specific model type’s query before the union is performed.

4. **Prevent Model Retrieval:**
    - **New Feature:**
      ```php
      $paginator->preventModelRetrieval()->paginate();
      ```

   With this option enabled, you receive raw records without the paginator attempting to load Eloquent models from the IDs. Transformations—if defined—are applied to the raw records directly.

5. **Mass Loading vs. N+1 Queries:**
   Previously, each record’s model would be loaded individually using `Model::find($id)` inside the transformation callback, leading to many queries. The new version batch-loads all records per model type using `findMany()`, significantly improving performance. No code changes are needed on your part to benefit from this; it’s an internal improvement.

6. **Selected Columns:**
    - **New Feature:**
      ```php
      $paginator->setSelectedColumns(User::class, ['id', 'email', DB::raw("'User' as type")]);
      ```

   This allows you to customize which columns are selected for each model type before building the union. This feature did not exist in the previous version.

7. **Exception Handling:**
   If you pass a non-model class to `forModels()`, an `InvalidArgumentException` is thrown. Ensure that all provided classes are valid Eloquent models.

## Example Before and After

**Before:**

```php
$paginator = UnionPaginator::for([User::class, Post::class])
    ->transform(User::class, function ($record) {
        return ['name' => $record->name];
    })
    ->paginate(10);
```

**After:**

```php
$paginator = UnionPaginator::forModels([User::class, Post::class])
    ->applyScope(User::class, fn($query) => $query->where('active', true))
    ->transformResultsFor(User::class, fn($user) => ['name' => strtoupper($user->name)])
    ->paginate(10);
```

Now the queries are built using Eloquent builders, and user models are filtered and transformed more cleanly. Additionally, all User and Post records are loaded in one go, eliminating N+1 queries.

## Summary

This migration provides a more fluent experience. You gain:
- More explicit method names. 
- The ability to scope queries per model. 
- Performance improvements by mass-loading models and avoiding N+1 queries. 
- Flexibility to opt-in or out of model retrieval. 
- Cleaner transformations and error handling.
