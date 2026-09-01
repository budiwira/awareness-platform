<?php

namespace App\Notifications;

use App\Models\PlanRequest;
use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillingRequestResolved extends Notification
{
    use Queueable;

    public function __construct(
        public ?PlanRequest $planRequest = null,
        public string $status = 'approved',
        public ?Plan $plan = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $planName = $this->plan->name ?? $this->planRequest->plan->name ?? 'Unknown';
        
        if ($this->status === 'approved') {
            $message = "Permintaan plan Anda telah disetujui. Plan baru: {$planName}";
        } else {
            $message = "Permintaan plan Anda ke {$planName} ditolak.";
        }

        return [
            'message' => $message,
            'request_id' => $this->planRequest?->id,
            'plan_name' => $planName,
            'status' => $this->status,
        ];
    }
}
