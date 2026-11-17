<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * La base refuse seule les états incohérents. Chaque test vérifie le nom de la contrainte
 * qui a refusé la ligne, pour ne pas réussir à cause d'une autre erreur.
 */

uses(RefreshDatabase::class);

/**
 * @param  Closure(): mixed  $statement
 */
function attemptUser(Closure $statement): void
{
    DB::transaction($statement);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userRow(array $overrides = []): array
{
    return array_merge([
        'id' => (string) Str::uuid7(),
        'name' => 'Alice',
        'email' => 'alice@example.org',
        'password' => 'hash',
    ], $overrides);
}

it('gives the role "user" to an account created without a role', function () {
    DB::table('users')->insert(userRow());

    expect(DB::table('users')->value('role'))->toBe('user');
});

it('refuses an unknown role', function () {
    expect(fn () => attemptUser(fn () => DB::table('users')->insert(userRow(['role' => 'superadmin']))))
        ->toThrow(QueryException::class, 'users_role_check');
});

it('refuses an address that is not in lowercase', function () {
    expect(fn () => attemptUser(fn () => DB::table('users')->insert(userRow(['email' => 'Alice@Example.org']))))
        ->toThrow(QueryException::class, 'users_email_lowercase');
});

it('refuses two accounts with the same address', function () {
    DB::table('users')->insert(userRow());

    expect(fn () => attemptUser(fn () => DB::table('users')->insert(userRow(['id' => (string) Str::uuid7()]))))
        ->toThrow(UniqueConstraintViolationException::class, 'users_email_unique');
});

it('uses a UUID column as the identifier, and for the session owner', function () {
    $types = DB::table('information_schema.columns')
        ->whereIn('table_name', ['users', 'sessions'])
        ->whereIn('column_name', ['id', 'user_id'])
        ->where('table_name', '!=', 'sessions')
        ->orWhere(fn ($query) => $query->where('table_name', 'sessions')->where('column_name', 'user_id'))
        ->pluck('data_type');

    expect($types->unique()->values()->all())->toBe(['uuid']);
});

it('has no mass-assignable role on the model', function () {
    $user = new User(['name' => 'A', 'email' => 'a@x.org', 'password' => 'x', 'role' => 'admin']);

    expect($user->role->value)->toBe('user');
});

it('refuses to recreate the users table when it contains accounts', function () {
    DB::table('users')->insert(userRow());
    $migration = require database_path('migrations/2026_11_17_110000_recreate_users_with_uuid_and_role.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'not empty')
        ->and(DB::table('users')->count())->toBe(1);
});
