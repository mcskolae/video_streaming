<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les assets ne sont pas compilés en CI : les tests ne doivent pas dépendre du manifeste Vite.
        $this->withoutVite();
    }
}
