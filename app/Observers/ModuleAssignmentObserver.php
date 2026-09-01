<?php

namespace App\Observers;

use App\Models\ModuleAssignment;
use App\Services\BadgeAwardService;

class ModuleAssignmentObserver
{
    public function __construct(
        private BadgeAwardService $badgeService
    ) {}

    /**
     * Handle the ModuleAssignment "updated" event.
     */
    public function updated(ModuleAssignment $assignment): void
    {
        // Cek badge saat assignment di-complete
        if ($assignment->status === 'completed') {
            $this->badgeService->checkModuleCompletion($assignment->user);
        }
    }
}
