<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn () => Role::firstOrCreate(['name' => User::ROLE_SUPER_ADMIN], ['description' => 'Synthetic factory role'])->id,
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'name' => fake()->name(),
            'status' => 1,
        ];
    }
}
