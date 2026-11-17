<?php

declare(strict_types=1);

namespace HomeFlix\Identity\UI\Http\Controllers;

use Illuminate\Contracts\View\View;

final class AdminDashboardController
{
    public function __invoke(): View
    {
        return view('identity::admin.dashboard');
    }
}
