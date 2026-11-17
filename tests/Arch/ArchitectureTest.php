<?php

declare(strict_types=1);

/*
 * Tests d'architecture : ils font respecter la règle hexagonale et l'isolation des contextes.
 * Si l'un d'eux échoue, c'est une dépendance qui va dans le mauvais sens.
 */

const CONTEXTS = ['Catalog', 'Media', 'Library', 'Identity'];
const FRAMEWORKS = ['Illuminate', 'Laravel', 'Carbon', 'Ramsey', 'Symfony', 'Livewire', 'Doctrine'];

arch('all source files declare strict types')
    ->expect('HomeFlix')
    ->toUseStrictTypes();

foreach ([...CONTEXTS, 'Shared'] as $context) {
    arch("{$context}: the domain is framework free")
        ->expect("HomeFlix\\{$context}\\Domain")
        ->not->toUse(FRAMEWORKS);

    arch("{$context}: the domain depends on no outer layer")
        ->expect("HomeFlix\\{$context}\\Domain")
        ->not->toUse([
            "HomeFlix\\{$context}\\Application",
            "HomeFlix\\{$context}\\Infrastructure",
            "HomeFlix\\{$context}\\UI",
        ]);

    arch("{$context}: the application layer depends on neither the framework nor adapters")
        ->expect("HomeFlix\\{$context}\\Application")
        ->not->toUse([
            ...FRAMEWORKS,
            "HomeFlix\\{$context}\\Infrastructure",
            "HomeFlix\\{$context}\\UI",
        ]);

    arch("{$context}: infrastructure does not depend on the UI")
        ->expect("HomeFlix\\{$context}\\Infrastructure")
        ->not->toUse("HomeFlix\\{$context}\\UI");
}

foreach (CONTEXTS as $context) {
    arch("Shared does not depend on {$context}")
        ->expect('HomeFlix\Shared')
        ->not->toUse("HomeFlix\\{$context}");

    foreach (CONTEXTS as $other) {
        if ($other === $context) {
            continue;
        }

        // Un contexte ne connaît d'un autre que ses événements de domaine.
        arch("{$context} only knows the domain events of {$other}")
            ->expect("HomeFlix\\{$context}")
            ->not->toUse([
                "HomeFlix\\{$other}\\Domain\\Model",
                "HomeFlix\\{$other}\\Domain\\Repository",
                "HomeFlix\\{$other}\\Domain\\Exception",
                "HomeFlix\\{$other}\\Application",
                "HomeFlix\\{$other}\\Infrastructure",
                "HomeFlix\\{$other}\\UI",
            ]);
    }
}
