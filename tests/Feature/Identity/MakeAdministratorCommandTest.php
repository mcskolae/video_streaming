<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Identity\Domain\Model\Role;
use Illuminate\Support\Facades\Hash;

const PASSWORD_LABEL = 'Mot de passe (12 caractères minimum)';

it('creates an administrator after asking for a name and a masked password', function () {
    $this->artisan('homeflix:make-admin', ['email' => 'Admin@Example.org'])
        ->expectsQuestion('Nom', 'Administrateur')
        ->expectsQuestion(PASSWORD_LABEL, 'a-long-enough-password')
        ->expectsQuestion('Confirmez le mot de passe', 'a-long-enough-password')
        ->expectsOutputToContain('Administrateur créé')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'admin@example.org')->firstOrFail();
    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->name)->toBe('Administrateur')
        ->and(Hash::check('a-long-enough-password', $admin->password))->toBeTrue();
});

it('creates nothing when the confirmation does not match', function () {
    $this->artisan('homeflix:make-admin', ['email' => 'admin@example.org'])
        ->expectsQuestion('Nom', 'Administrateur')
        ->expectsQuestion(PASSWORD_LABEL, 'a-long-enough-password')
        ->expectsQuestion('Confirmez le mot de passe', 'another-password-entirely')
        ->expectsOutputToContain('ne correspondent pas')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('promotes an existing account without asking anything', function () {
    $user = User::factory()->create(['email' => 'alice@example.org']);
    $hashBefore = $user->password;

    $this->artisan('homeflix:make-admin', ['email' => 'alice@example.org'])
        ->expectsOutputToContain('maintenant administrateur')
        ->assertSuccessful();

    $user->refresh();
    expect($user->role)->toBe(Role::Admin)
        ->and($user->password)->toBe($hashBefore);
});

it('warns when the account is already an administrator', function () {
    User::factory()->admin()->create(['email' => 'root@example.org']);

    $this->artisan('homeflix:make-admin', ['email' => 'root@example.org'])
        ->expectsOutputToContain('déjà administrateur')
        ->assertSuccessful();
});

it('rejects an invalid address', function () {
    $this->artisan('homeflix:make-admin', ['email' => 'not-an-email'])
        ->expectsOutputToContain('not a valid email')
        ->assertFailed();
});

it('does not put the password in the command line or the output', function () {
    $this->artisan('homeflix:make-admin', ['email' => 'admin@example.org'])
        ->expectsQuestion('Nom', 'Administrateur')
        ->expectsQuestion(PASSWORD_LABEL, 'a-long-enough-password')
        ->expectsQuestion('Confirmez le mot de passe', 'a-long-enough-password')
        ->doesntExpectOutputToContain('a-long-enough-password')
        ->assertSuccessful();
});
