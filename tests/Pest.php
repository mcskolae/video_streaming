<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Integration');

// Les tests Feature utilisent la base à chaque fois. Les tests d'intégration déclarent
// RefreshDatabase seulement quand ils en ont besoin.
pest()->use(RefreshDatabase::class)->in('Feature');
