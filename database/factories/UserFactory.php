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
            'phone' => fake()->numerify('3##-###-####'),
            'address' => fake()->address(),
            'cedula' => fake()->unique()->numerify('##########'),
            'experience' => null,
            'title' => null,
            'image' => null,
            'role' => 'client',
        ];
    }

    /**
     * Estado: Cliente.
     * Campos: nombre, correo, celular, dirección, cédula, imagen.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => fake()->numerify('3##-###-####'),
            'address' => fake()->address(),
            'cedula' => fake()->unique()->numerify('##########'),
            'experience' => null,
            'title' => null,
            'image' => null,
            'role' => 'client',
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('client');
        });
    }

    /**
     * Estado: Técnico.
     * Campos: nombre, cédula, correo, dirección, experiencia, título, imagen.
     */
    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
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
            'phone' => null,
            'image' => null,
            'role' => 'technician',
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('technician');
        });
    }

    /**
     * Estado: Administrador.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => fake()->numerify('3##-###-####'),
            'address' => fake()->address(),
            'cedula' => fake()->unique()->numerify('##########'),
            'experience' => null,
            'title' => null,
            'image' => null,
            'role' => 'admin',
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('admin');
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
