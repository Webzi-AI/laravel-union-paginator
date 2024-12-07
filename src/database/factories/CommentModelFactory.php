<?php

namespace AustinW\UnionPaginator\database\factories;

use AustinW\UnionPaginator\Tests\TestClasses\Models\CommentModel;
use AustinW\UnionPaginator\Tests\TestClasses\Models\PostModel;
use AustinW\UnionPaginator\Tests\TestClasses\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentModelFactory extends Factory
{
    protected $model = CommentModel::class;
    public function definition()
    {
        return [
            'user_id' => UserModel::factory(),
            'post_id' => PostModel::factory(),
            'content' => $this->faker->paragraph,
        ];
    }
}
