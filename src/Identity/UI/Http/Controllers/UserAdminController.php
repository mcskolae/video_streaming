<?php

declare(strict_types=1);

namespace HomeFlix\Identity\UI\Http\Controllers;

use HomeFlix\Identity\Application\Command\ChangeUserRole;
use HomeFlix\Identity\Application\Command\ChangeUserRoleHandler;
use HomeFlix\Identity\Application\Command\ReactivateUser;
use HomeFlix\Identity\Application\Command\ReactivateUserHandler;
use HomeFlix\Identity\Application\Command\SuspendUser;
use HomeFlix\Identity\Application\Command\SuspendUserHandler;
use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Application\Query\ListUsers;
use HomeFlix\Identity\Application\Query\ListUsersHandler;
use HomeFlix\Identity\Domain\Exception\CannotRemoveLastAdministrator;
use HomeFlix\Identity\Domain\Model\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Gestion des comptes. Toutes les routes passent par `auth` puis `admin` (voir routes.php). */
final class UserAdminController
{
    public function index(Request $request, ListUsersHandler $handler): View
    {
        $page = $handler->handle(new ListUsers(max(1, $request->integer('page', 1))));

        return view('identity::admin.users.index', [
            'users' => new LengthAwarePaginator($page->rows, $page->total, $page->perPage, $page->page, ['path' => $request->url()]),
            'currentUserId' => (string) $request->user()?->getAuthIdentifier(),
        ]);
    }

    public function changeRole(Request $request, string $user, ChangeUserRoleHandler $handler): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'string', Rule::in(array_map(fn (Role $role) => $role->value, Role::cases()))]]);

        return $this->run(
            fn () => $handler->handle(new ChangeUserRole($user, $data['role'])),
            'Rôle mis à jour.',
        );
    }

    public function suspend(string $user, SuspendUserHandler $handler): RedirectResponse
    {
        return $this->run(fn () => $handler->handle(new SuspendUser($user)), 'Compte suspendu. Sa session sera fermée à sa prochaine requête.');
    }

    public function reactivate(string $user, ReactivateUserHandler $handler): RedirectResponse
    {
        return $this->run(fn () => $handler->handle(new ReactivateUser($user)), 'Compte réactivé.');
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (UserNotFound|InvalidArgumentException) {
            abort(404);
        } catch (CannotRemoveLastAdministrator) {
            return back()->with('error', 'Impossible : c\'est le dernier administrateur actif. Donnez d\'abord le rôle à un autre compte.');
        }

        return back()->with('status', $success);
    }
}
