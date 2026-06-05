<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(Database\Seeders\RolePermissionSeeder::class);
    })
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(Database\Seeders\RolePermissionSeeder::class);
    })
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function clientUser(array $attributes = []): \App\Models\User
{
    return \App\Models\User::factory()->client()->create($attributes);
}

function technicianUser(array $attributes = []): \App\Models\User
{
    return \App\Models\User::factory()->technician()->create($attributes);
}

function serviceCaseFor(\App\Models\User $clientUser, array $attributes = []): \App\Models\ServiceCase
{
    return \App\Models\ServiceCase::create(array_merge([
        'client_id' => $clientUser->client->id,
        'title' => 'Reparar computador',
        'description' => 'El equipo no enciende correctamente.',
        'service_type' => 'presential',
        'city' => 'Bogota',
        'status' => 'active',
    ], $attributes));
}

function caseResponseFor(
    \App\Models\ServiceCase $serviceCase,
    \App\Models\User $technicianUser,
    array $attributes = []
): \App\Models\CaseResponse {
    return \App\Models\CaseResponse::create(array_merge([
        'service_case_id' => $serviceCase->id,
        'technician_id' => $technicianUser->technician->id,
        'estimated_cost' => 120000,
        'questions' => 'Puedo revisarlo hoy en la tarde.',
    ], $attributes));
}

function adminUser(array $attributes = []): \App\Models\User
{
    return \App\Models\User::factory()->admin()->create($attributes);
}

function superAdminUser(array $attributes = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create($attributes);
    $user->assignRole('super_admin');
    \App\Models\Admin::create(['user_id' => $user->id]);

    return $user;
}

function moderatorUser(array $attributes = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create($attributes);
    $user->assignRole('moderator');
    \App\Models\Admin::create(['user_id' => $user->id]);

    return $user;
}
