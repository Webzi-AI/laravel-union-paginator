<?php

namespace AustinW\UnionPaginator\Tests;

use AustinW\UnionPaginator\Tests\TestClasses\Models\PostModel;
use AustinW\UnionPaginator\Tests\TestClasses\Models\UserModel;
use AustinW\UnionPaginator\UnionPaginator;
use BadMethodCallException;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Factories\Sequence;

class UnionPaginatorTest extends TestCase
{
    protected UserModel $post;

    protected UserModel $comment;

    // Constructor creates union query from multiple model types with correct table and type selection
    public function test_constructor_creates_union_query_with_correct_table_and_type(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        $this->assertNotNull($paginator->unionQuery);
        $this->assertEquals(
            'select * from (select "id", "created_at", \''.UserModel::class.'\' as type from "user_models" where "deleted_at" is null) union select * from (select "id", "created_at", \''.PostModel::class.'\' as type from "post_models" where "deleted_at" is null)',
            $paginator->unionQuery->toSql()
        );
        $this->assertInstanceOf(Builder::class, $paginator->unionQuery);
    }

    // Constructor handles empty model types array
    public function test_constructor_handles_empty_model_types(): void
    {
        $paginator = new UnionPaginator([]);

        $this->assertNull($paginator->unionQuery);
        $this->assertEmpty($paginator->getModelTypes());
        $this->assertEmpty($paginator->through);
    }

    public function test_paginate_returns_a_length_aware_paginator(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        UserModel::factory()->count(3)->create();
        PostModel::factory()->count(3)->create();

        $result = $paginator->paginate();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_transform_method_applies_closure_to_model_type(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        // Note: Each post will create a user record
        PostModel::factory()->count(3)->create();

        $result = $paginator->transform(UserModel::class, fn($record) => ['foo' => 'test'])->paginate();

        $this->assertEquals(PostModel::find(1), $result->items()[0]);
        $this->assertEquals(['foo' => 'test'], $result->items()[1]);
        $this->assertEquals(PostModel::find(2), $result->items()[2]);
        $this->assertEquals(['foo' => 'test'], $result->items()[3]);
        $this->assertEquals(PostModel::find(3), $result->items()[4]);
        $this->assertEquals(['foo' => 'test'], $result->items()[5]);
    }

    public function test_soft_deletes_filters_out_soft_deleted_records(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        // Note: Each post will create a user record
        PostModel::factory()->count(3)->create();

        UserModel::find(1)->delete();
        PostModel::find(2)->delete();

        $this->assertSoftDeleted(UserModel::find(1));
        $this->assertSoftDeleted(PostModel::find(2));

        $result = $paginator->paginate();

        $this->assertEquals(4, $result->total());
    }

    public function test_static_for_method_instantiates_with_model_types_array(): void
    {
        $paginator = UnionPaginator::for([UserModel::class, PostModel::class]);

        $this->assertNotNull($paginator->unionQuery);
        $this->assertEquals([UserModel::class, PostModel::class], $paginator->getModelTypes());
    }

    public function test_paginate_handles_empty_result_set(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        $result = $paginator->paginate();

        $this->assertEquals(0, $result->total());
    }

    public function test_transform_handles_non_existent_model_type(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        $result = $paginator->transform('foo', fn($record) => ['foo' => 'test'])->paginate();

        $this->assertEquals([], $result->items());

        $this->assertEmpty($paginator->through);

        $this->assertEquals([UserModel::class, PostModel::class], $paginator->getModelTypes());
    }

    public function test_method_forwarding_fails_for_null_union_query()
    {
        $paginator = new UnionPaginator([]);

        $this->expectException(BadMethodCallException::class);

        $paginator->paginate();
    }

    public function test_soft_deletes_with_invalid_model_types_array()
    {
        $this->expectException(BadMethodCallException::class);

        new UnionPaginator(['foo', 'bar']);
    }

    public function test_constructor_properly_escapes_model_type_names_in_raw_sql()
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        $this->assertStringContainsString(
            '\''.UserModel::class.'\'',
            $paginator->unionQuery->toSql()
        );

        $this->assertStringContainsString(
            '\''.PostModel::class.'\'',
            $paginator->unionQuery->toSql()
        );

        $this->assertStringNotContainsString(
            ' '.UserModel::class.' ',
            $paginator->unionQuery->toSql()
        );

        $this->assertStringNotContainsString(
            ' '.PostModel::class.' ',
            $paginator->unionQuery->toSql()
        );
    }

    public function test_paginate_respects_custom_per_page_value()
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        UserModel::factory()->count(3)->create();
        PostModel::factory()->count(3)->create();

        $result = $paginator->paginate(2);

        $this->assertEquals(2, $result->perPage());
        $this->assertEquals(2, $result->count());
        $this->assertEquals(9, $result->total());
    }

    public function test_transform_closure_receives_expected_record_format()
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        UserModel::factory()->count(3)->create();
        PostModel::factory()->count(3)->create();

        $result = $paginator->transform(UserModel::class, function ($record) {
            $this->assertEquals(['id', 'created_at', 'type'], array_keys((array) $record));

            return $record;
        })->paginate();

        $this->assertEquals(9, $result->total());

        $this->assertNotEmpty($paginator->through);
        $this->assertEquals([UserModel::class, PostModel::class], $paginator->getModelTypes());
    }

    public function test_pagination_maintains_proper_binding_order_for_complex_unions()
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        UserModel::factory()->count(2)->create();
        PostModel::factory()->count(2)->create();

        $result = $paginator->paginate();

        $this->assertEquals(6, $result->total());
        $this->assertEquals(
            [
                PostModel::find(1),
                UserModel::find(1),
                PostModel::find(2),
                UserModel::find(2),
                UserModel::find(3),
                UserModel::find(4),
            ],
            $result->items()
        );
    }

    public function test_it_supports_ordering_models()
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        $firstUser = UserModel::factory()->create(['created_at' => now()]);
        $firstPost = PostModel::factory()->for($firstUser, 'user')->create(['created_at' => now()->addSecond()]);

        $this->travel(1)->seconds();

        $secondUser = UserModel::factory()->create(['created_at' => now()->addDay()]);
        $secondPost = PostModel::factory()->for($secondUser, 'user')->create(['created_at' => now()->addDay()->addSecond()]);

        $result = $paginator->latest()->paginate();

        $items = $result->items();

        $this->assertEquals($secondPost->id, $items[0]->id);
        $this->assertEquals(PostModel::class, get_class($items[0]));

        $this->assertEquals($secondUser->id, $items[1]->id);
        $this->assertEquals(UserModel::class, get_class($items[1]));

        $this->assertEquals($firstPost->id, $items[2]->id);
        $this->assertEquals(PostModel::class, get_class($items[2]));

        $this->assertEquals($firstUser->id, $items[3]->id);
        $this->assertEquals(UserModel::class, get_class($items[3]));
    }

    public function test_empty_closure_for_transform(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        PostModel::factory()->count(3)->create();

        $result = $paginator->transform(UserModel::class, fn($record) => null)->paginate();

        $items = $result->items();

        $this->assertInstanceOf(PostModel::class, $items[0]);
        $this->assertNull($items[1]);
        $this->assertInstanceOf(PostModel::class, $items[2]);
        $this->assertNull($items[3]);
        $this->assertInstanceOf(PostModel::class, $items[4]);
        $this->assertNull($items[5]);
    }

    public function test_multiple_transforms_on_same_model(): void
    {
        $paginator = new UnionPaginator([UserModel::class]);

        UserModel::factory()->count(3)->create();

        $paginator->transform(UserModel::class, fn($record) => (object) ['id' => $record->id, 'transformed' => true]);
        $result = $paginator->transform(UserModel::class, fn($record) => (object) ['id' => $record->id, 'overridden' => true])->paginate();

        foreach ($result->items() as $item) {
            $itemArray = (array) $item;
            $this->assertArrayHasKey('overridden', $itemArray);
            $this->assertArrayNotHasKey('transformed', $itemArray);
        }
    }

    public function test_union_with_large_tables(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        PostModel::factory()->count(100)->create();

        $result = $paginator->paginate(50);

        $this->assertEquals(50, $result->count());
        $this->assertEquals(200, $result->total());
    }

    public function test_transform_with_invalid_model_type(): void
    {
        $paginator = new UnionPaginator([UserModel::class]);

        $result = $paginator->transform(PostModel::class, fn($record) => ['invalid' => true])->paginate();

        $this->assertEmpty($paginator->through);
        $this->assertCount(0, $result->items());
    }

    public function test_boundary_conditions_for_pagination(): void
    {
        $paginator = new UnionPaginator([UserModel::class]);

        UserModel::factory()->count(10)->create();

        $result = $paginator->paginate(10);

        $this->assertEquals(10, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(1, $result->currentPage());

        $result = $paginator->paginate(5);

        $this->assertEquals(2, $result->lastPage());
    }

    public function test_pagination_beyond_total_results(): void
    {
        $paginator = new UnionPaginator([UserModel::class]);

        UserModel::factory()->count(5)->create();

        $result = $paginator->paginate(5, ['*'], 'page', 2);

        $this->assertEmpty($result->items());
        $this->assertEquals(0, $result->count());
        $this->assertEquals(5, $result->total());
    }

    public function test_combined_sorting_rules(): void
    {
        $paginator = new UnionPaginator([UserModel::class, PostModel::class]);

        $users = UserModel::factory()->count(3)->create(['created_at' => now()->subMinutes(10)]);

        $posts = PostModel::factory()
            ->count(3)
            ->state(new Sequence(
                ['user_id' => $users[0]->id],
                ['user_id' => $users[1]->id],
                ['user_id' => $users[2]->id],
            ))
            ->create(['created_at' => now()->subMinutes(5)]);

        $items = $paginator->latest()->paginate()->items();

        $this->assertCount(6, $items);

        // Ensure the sorting order by created_at
        $this->assertEquals(PostModel::class, get_class($items[0])); // Posts should be first (newer timestamps)
        $this->assertEquals(UserModel::class, get_class($items[3])); // Explicit Users follow (older timestamps)

        // Further assertions for clarity
        foreach ($posts as $post) {
            $this->assertContains($post->id, array_column($items, 'id'));
        }

        foreach ($users as $user) {
            $this->assertContains($user->id, array_column($items, 'id'));
        }
    }
}
