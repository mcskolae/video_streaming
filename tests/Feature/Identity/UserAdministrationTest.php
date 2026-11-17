<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Chef']);
});

describe('access', function () {
    $routes = [
        'list' => ['get', '/admin/users'],
        'role' => ['post', '/admin/users/01900000-0000-7000-8000-000000000000/role'],
        'suspend' => ['post', '/admin/users/01900000-0000-7000-8000-000000000000/suspend'],
        'reactivate' => ['post', '/admin/users/01900000-0000-7000-8000-000000000000/reactivate'],
    ];

    it('sends a visitor to the login page', function (string $method, string $uri) {
        $this->{$method}($uri)->assertRedirect('/login');
    })->with($routes);

    it('refuses a simple user with a 403 and changes nothing', function (string $method, string $uri) {
        $member = User::factory()->create();

        $this->actingAs($member)->{$method}(str_replace('01900000-0000-7000-8000-000000000000', $member->id, $uri), ['role' => 'admin'])->assertForbidden();

        expect($member->fresh()->role->value)->toBe('user')->and($member->fresh()->suspended_at)->toBeNull();
    })->with($routes);

    it('protects every route under /admin/users with auth, admin and the web group', function () {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin/users'));

        expect($routes)->toHaveCount(4);

        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('auth', 'admin', 'web');
        }
    });
});

describe('list', function () {
    it('shows every account with its role and state', function () {
        User::factory()->create(['name' => 'Alice Membre', 'email' => 'alice@example.org']);
        User::factory()->suspended()->create(['name' => 'Bob Suspendu']);

        $this->actingAs($this->admin)->get('/admin/users')->assertOk()
            ->assertSee('Alice Membre')->assertSee('alice@example.org')
            ->assertSee('Bob Suspendu')->assertSee('Suspendu')->assertSee('Actif')
            ->assertSee('Administrateur')->assertSee('Utilisateur')
            ->assertSee('(vous)');
    });

    it('escapes names', function () {
        User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin)->get('/admin/users')->assertDontSee('<script>alert(1)</script>', false);
    });

    it('is linked from the navigation, for administrators only', function () {
        $this->actingAs($this->admin)->get('/dashboard')->assertSee(route('admin.users.index'), false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee(route('admin.users.index'), false);
    });
});

describe('role', function () {
    it('promotes and demotes', function () {
        $member = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.role', $member->id), ['role' => 'admin'])->assertSessionHas('status');
        expect($member->fresh()->role->value)->toBe('admin');

        $this->actingAs($this->admin)->post(route('admin.users.role', $member->id), ['role' => 'user'])->assertSessionHas('status');
        expect($member->fresh()->role->value)->toBe('user');
    });

    it('refuses to demote the last administrator, even yourself', function () {
        $this->actingAs($this->admin)->post(route('admin.users.role', $this->admin->id), ['role' => 'user'])
            ->assertSessionHas('error');

        expect($this->admin->fresh()->role->value)->toBe('admin');
    });

    it('lets an administrator demote themselves when another one exists', function () {
        User::factory()->admin()->create();

        $this->actingAs($this->admin)->post(route('admin.users.role', $this->admin->id), ['role' => 'user'])->assertSessionHas('status');

        expect($this->admin->fresh()->role->value)->toBe('user');
    });

    it('refuses an unknown role', function (mixed $role) {
        $member = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.role', $member->id), ['role' => $role])->assertSessionHasErrors('role');
        expect($member->fresh()->role->value)->toBe('user');
    })->with(['root', '', null, [['admin']]]);

    it('is not found for an unknown account or an identifier that is not a UUID', function () {
        $this->actingAs($this->admin)->post(route('admin.users.role', Uuid::uuid7()->toString()), ['role' => 'admin'])->assertNotFound();
        $this->actingAs($this->admin)->post('/admin/users/pas-un-uuid/role', ['role' => 'admin'])->assertNotFound();
    });

    it('cannot be given through the profile form', function () {
        $member = User::factory()->create();

        $this->actingAs($member)->patch('/profile', ['name' => 'X', 'email' => $member->email, 'role' => 'admin', 'suspended_at' => null]);

        expect($member->fresh()->role->value)->toBe('user');
    });
});

describe('suspension', function () {
    it('suspends and reactivates an account', function () {
        $member = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.suspend', $member->id))->assertSessionHas('status');
        expect($member->fresh()->suspended_at)->not->toBeNull();

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $member->id))->assertSessionHas('status');
        expect($member->fresh()->suspended_at)->toBeNull();
    });

    it('refuses to suspend the last administrator, even yourself', function () {
        $this->actingAs($this->admin)->post(route('admin.users.suspend', $this->admin->id))->assertSessionHas('error');

        expect($this->admin->fresh()->suspended_at)->toBeNull();
    });

    it('refuses to demote the last active administrator when the other one is suspended', function () {
        User::factory()->admin()->suspended()->create();

        $this->actingAs($this->admin)->post(route('admin.users.role', $this->admin->id), ['role' => 'user'])->assertSessionHas('error');
        expect($this->admin->fresh()->role->value)->toBe('admin');
    });

    it('prevents a suspended account from signing in, without saying why', function () {
        $member = User::factory()->suspended()->create(['email' => 'bob@example.org']);

        $this->post('/login', ['email' => 'bob@example.org', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
        expect(session('errors')->first('email'))->toBe(trans('auth.failed'));
    });

    it('lets a reactivated account sign in again', function () {
        $member = User::factory()->suspended()->create(['email' => 'bob@example.org']);
        $member->forceFill(['suspended_at' => null])->save();

        $this->post('/login', ['email' => 'bob@example.org', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($member);
    });

    it('closes the session of an account suspended while it was signed in', function () {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/dashboard')->assertOk();

        $this->actingAs($this->admin)->post(route('admin.users.suspend', $member->id));
        auth()->logout();

        $this->actingAs($member->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    });

    it('closes the session on every kind of request, public pages and JSON included', function () {
        $member = User::factory()->suspended()->create();

        $this->actingAs($member)->get('/')->assertRedirect('/login');

        $this->actingAs($member)->putJson('/progression/'.Uuid::uuid7()->toString(), ['position' => 1])->assertUnauthorized();
        $this->assertGuest();
    });

    it('does not let a suspended administrator reach the administration', function () {
        $suspended = User::factory()->admin()->suspended()->create();

        $this->actingAs($suspended)->get('/admin/users')->assertRedirect('/login');
    });

    it('also closes a session restored from the remember-me cookie', function () {
        $member = User::factory()->create(['email' => 'bob@example.org']);
        $name = Auth::guard('web')->getRecallerName();

        // Une vraie connexion « se souvenir de moi » : on garde le cookie chiffré que le serveur a posé.
        $login = $this->post('/login', ['email' => 'bob@example.org', 'password' => 'password', 'remember' => 'on']);
        $recaller = $login->getCookie($name, false)->getValue();

        $restore = function () use ($name, $recaller) {
            auth()->forgetGuards();
            $this->flushSession();

            return $this->call('GET', '/dashboard', [], [$name => $recaller]);
        };

        // Le cookie seul ouvre bien une session tant que le compte est actif…
        $restore()->assertOk();
        $this->assertAuthenticatedAs($member);

        // …et plus dès qu'il est suspendu.
        $member->forceFill(['suspended_at' => now()])->save();

        $restore()->assertRedirect('/login');
        $this->assertGuest();
    });
});

describe('the database', function () {
    it('keeps the suspension date', function () {
        $member = User::factory()->create();
        $this->actingAs($this->admin)->post(route('admin.users.suspend', $member->id));

        expect(DB::table('users')->where('id', $member->id)->value('suspended_at'))->not->toBeNull();
    });
});
