<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            // El rol de menor privilegio por defecto: si un test se olvida de declararlo,
            // que falle por falta de permisos y no por tenerlos de más.
            'rol' => UserRole::Editor,
        ];
    }

    /** El administrador es también el validador lingüístico. */
    public function administrador(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => UserRole::Administrator,
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => UserRole::Editor,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
