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
        $colombianCities = [
            'Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena',
            'Cúcuta', 'Bucaramanga', 'Pereira', 'Santa Marta', 'Ibagué',
            'Pasto', 'Manizales', 'Neiva', 'Villavicencio', 'Armenia',
            'Valledupar', 'Montería', 'Sincelejo', 'Popayán', 'Floridablanca',
            'Palmira', 'Buenaventura', 'Itagüí', 'Envigado', 'Rionegro'
        ];

        $name = fake()->name();

        return [
            'name'              => $name,
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => fake()->numerify('3##-###-####'),
            'city'              => fake()->randomElement($colombianCities),
            'address'           => fake()->address(),
            'type_id'           => 'CC', // Cédula de Ciudadanía
            'id_number'         => fake()->unique()->numerify('##########'),
            'image'             => 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . urlencode($name),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Estado: Cliente.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('client');
            \App\Models\Client::create([
                'user_id' => $user->id,
            ]);
        });
    }

    /**
     * Estado: Técnico.
     */
    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('technician');
            \App\Models\Technician::create([
                'user_id'    => $user->id,
                'experience' => fake()->paragraph(2),
                'title'      => fake()->randomElement([
                    'Ing. Electrónico',
                    'Técnico en Refrigeración',
                    'Técnico en Sistemas',
                    'Ing. Mecatrónico',
                    'Técnico Electricista',
                ]),
                'working_hours' => fake()->randomElement([
                    'Lunes a Viernes 8:00 AM - 6:00 PM',
                    'Lunes a Sábado 7:00 AM - 5:00 PM',
                    'Lunes a Viernes 9:00 AM - 7:00 PM',
                    '24/7 Emergencias',
                    'Fines de Semana y Festivos',
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
