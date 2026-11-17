<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use HomeFlix\Identity\Application\Port\UserListReader;
use HomeFlix\Identity\Application\Query\UserPage;
use HomeFlix\Identity\Application\Query\UserRow;
use HomeFlix\Identity\Domain\Model\Role;
use Illuminate\Support\Facades\DB;

final class DatabaseUserListReader implements UserListReader
{
    public function page(int $page, int $perPage): UserPage
    {
        $rows = DB::table('users')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(['id', 'name', 'email', 'role', 'suspended_at', 'created_at']);

        return new UserPage(
            $rows->map(fn (object $row) => new UserRow(
                (string) $row->id,
                (string) $row->name,
                (string) $row->email,
                Role::from((string) $row->role),
                $row->suspended_at === null ? null : new DateTimeImmutable((string) $row->suspended_at),
                new DateTimeImmutable((string) $row->created_at),
            ))->values()->all(),
            DB::table('users')->count(),
            $page,
            $perPage,
        );
    }
}
