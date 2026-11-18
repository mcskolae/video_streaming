<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

it('redirects a visitor to the login page', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('refuses a simple user with a 403', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('sends an administrator from /admin to the catalogue', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertRedirect(route('admin.titles.index'));
});

it('shows the administration link only to administrators', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('admin.titles.index'), false);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('admin.titles.index'), false);
});

it('protects every route under /admin with authentication and the admin check', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'));

    expect($adminRoutes)->not->toBeEmpty();

    foreach ($adminRoutes as $route) {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('auth', 'admin', 'web');
    }
});

it('keeps the guest-only pages away from a logged-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect();
});
