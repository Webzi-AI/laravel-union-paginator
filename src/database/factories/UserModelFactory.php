<?php

namespace AustinW\UnionPaginator\database\factories;

use AustinW\UnionPaginator\Tests\TestClasses\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserModelFactory extends Factory
{
    protected $model = UserModel::class;
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'password' => $this->faker->password,
        ];
    }
}
