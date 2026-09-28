<?php

test('primary navigation exposes only the reconciled tabletop destinations', function () {
    $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
    $tabletopNav = file_get_contents(resource_path('js/Components/TabletopNav.vue'));
    $tenantDashboard = file_get_contents(resource_path('js/Pages/Tenant/Dashboard.vue'));

    expect($layout)
        ->toContain("{ label: 'Tabletop Scenarios', route: 'platform.ttx.scenarios.index' }")
        ->toContain("{ label: 'Tabletop — Sessions', route: 'tenant.ttx.sessions.index'")
        ->toContain("{ label: 'Tabletop — My Exercises', route: 'user.ttx.index'")
        ->not->toContain("route: 'tenant.ttx.index'")
        ->not->toContain("route: 'tenant.ttx.exercises.index'")
        ->not->toContain('Playbooks', 'Runbooks', 'Simulasi TTX');

    expect($tabletopNav)
        ->toContain("route('tenant.ttx.sessions.index')")
        ->not->toContain('playbook', 'runbook', 'exercises');

    expect($tenantDashboard)
        ->toContain("route('tenant.ttx.sessions.index')")
        ->not->toContain("route('tenant.ttx.exercises.index')");
});
