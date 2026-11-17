<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Identity\Domain\Model\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Support\CatalogFixtures;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Alice',
        'email' => 'alice@example.org',
        'password' => 'a-long-enough-password',
        'password_confirmation' => 'a-long-enough-password',
    ], $overrides);
}

describe('registration', function () {
    it('creates a simple user, hashes the password and logs them in', function () {
        $this->post('/register', registrationData())->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'alice@example.org')->firstOrFail();
        expect($user->role)->toBe(Role::User)
            ->and(Hash::check('a-long-enough-password', $user->password))->toBeTrue()
            ->and($user->password)->not->toContain('a-long-enough-password')
            ->and(CatalogFixtures::uuidVersion($user->id))->toBe(7);
        $this->assertAuthenticatedAs($user);
    });

    it('cannot be used to become an administrator', function (array $extra) {
        $this->post('/register', registrationData($extra));

        expect(User::query()->where('email', 'alice@example.org')->firstOrFail()->role)->toBe(Role::User);
    })->with([
        'role' => [['role' => 'admin']],
        'is_admin' => [['is_admin' => '1']],
        'role array' => [['role' => ['admin']]],
    ]);

    it('stores the address in lowercase', function () {
        $this->post('/register', registrationData(['email' => '  Alice@Example.ORG ']))->assertRedirect('/dashboard');

        expect(User::query()->value('email'))->toBe('alice@example.org');
    });

    it('refuses an address already used with a different case', function () {
        User::factory()->create(['email' => 'alice@example.org']);

        $this->post('/register', registrationData(['email' => 'ALICE@example.org']))
            ->assertSessionHasErrors('email');

        expect(User::query()->count())->toBe(1);
    });

    it('refuses a weak password', function () {
        $this->post('/register', registrationData(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        expect(User::query()->count())->toBe(0);
    });
});

describe('login and logout', function () {
    it('accepts an address typed with another case', function () {
        User::factory()->create(['email' => 'alice@example.org']);

        $this->post('/login', ['email' => 'Alice@Example.org', 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    });

    it('refuses a wrong password', function () {
        User::factory()->create(['email' => 'alice@example.org']);

        $this->post('/login', ['email' => 'alice@example.org', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('logs out', function () {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    });
});

describe('rate limiting', function () {
    beforeEach(fn () => RateLimiter::clear('throttle'));

    it('limits registrations per IP address', function () {
        foreach (range(1, 10) as $i) {
            $this->post('/register', registrationData(['email' => "user{$i}@example.org"]))->assertRedirect();
            auth()->logout();
        }

        $this->post('/register', registrationData(['email' => 'user11@example.org']))->assertStatus(429);
    });

    it('limits logins per IP address', function () {
        foreach (range(1, 20) as $i) {
            $this->post('/login', ['email' => "nobody{$i}@example.org", 'password' => 'x'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'nobody21@example.org', 'password' => 'x'])->assertStatus(429);
    });

    it('locks an address after five failed attempts', function () {
        User::factory()->create(['email' => 'alice@example.org']);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'alice@example.org', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'alice@example.org', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    });
});

describe('profile', function () {
    it('cannot be used to change one\'s own role', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Alice',
            'email' => $user->email,
            'role' => 'admin',
        ]);

        expect($user->fresh()->role)->toBe(Role::User);
    });
});

describe('flows removed on purpose', function () {
    it('has no password reset, email verification or password confirmation route', function (string $name) {
        expect(Route::has($name))->toBeFalse();
    })->with(['password.request', 'password.reset', 'password.email', 'verification.notice', 'verification.send', 'password.confirm']);
});
