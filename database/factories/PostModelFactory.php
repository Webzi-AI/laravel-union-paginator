<?php

namespace AustinW\UnionPaginator\Database\Factories;

use AustinW\UnionPaginator\Tests\TestClasses\Models\PostModel;
use AustinW\UnionPaginator\Tests\TestClasses\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostModelFactory extends Factory
{
    protected $model = PostModel::class;
    public function definition()
    {
        return [
            'user_id' => UserModel::factory(),
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraph,
        ];
    }
}
