<?php

namespace App\Observers;

use App\Models\ModuleAssignment;
use App\Services\BadgeAwardService;
use App\Support\Audit\Audit;

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
        $changes = array_intersect_key($assignment->getChanges(), array_flip([
            'score', 'status', 'completed_at', 'pretest_score', 'pretest_completed_at',
        ]));

        if ($changes !== []) {
            Audit::log('assignment.result_changed', $assignment, [
                'module_id' => $assignment->training_module_id,
                'user_id' => $assignment->user_id,
                'before' => array_intersect_key($assignment->getRawOriginal(), $changes),
                'after' => $changes,
            ]);
        }

        // Cek badge saat assignment di-complete
        if ($assignment->status === 'completed') {
            $this->badgeService->checkModuleCompletion($assignment->user);
        }
    }
}
