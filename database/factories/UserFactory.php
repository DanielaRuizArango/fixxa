<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state (cliente por defecto).
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
            'role' => 'client',
        ];
    }

    /**
     * Estado: Cliente.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('client');
            \App\Models\Client::create([
                'user_id' => $user->id,
                'phone' => fake()->numerify('3##-###-####'),
                'address' => fake()->address(),
                'cedula' => fake()->unique()->numerify('##########'),
            ]);
        });
    }

    /**
     * Estado: Técnico.
     */
    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'technician',
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('technician');
            \App\Models\Technician::create([
                'user_id' => $user->id,
                'address' => fake()->address(),
                'cedula' => fake()->unique()->numerify('##########'),
                'experience' => fake()->paragraph(2),
                'title' => fake()->randomElement([
                    'Ing. Electrónico',
                    'Técnico en Refrigeración',
                    'Técnico en Sistemas',
                    'Ing. Mecatrónico',
                    'Técnico Electricista',
                ]),
            ]);
        });
    }

    /**
     * Estado: Administrador.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('admin');
            \App\Models\Admin::create([
                'user_id' => $user->id,
            ]);
        });
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
