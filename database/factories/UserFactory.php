<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'login_id' => fake()->unique()->numerify('E#####'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_CONSTRUCTION,
            'is_active' => true,
        ];
    }

    public function designer(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_DESIGNER]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }
}
