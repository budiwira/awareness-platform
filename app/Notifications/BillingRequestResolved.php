<?php

namespace App\Notifications;

use App\Models\PackageRequest;
use App\Models\Package;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillingRequestResolved extends Notification
{
    use Queueable;

    public function __construct(
        public ?PackageRequest $PackageRequest = null,
        public string $status = 'approved',
        public ?Package $Package = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $planName = $this->Package->name ?? $this->PackageRequest->Package->name ?? 'Unknown';
        
        if ($this->status === 'approved') {
            $message = "Permintaan Package Anda telah disetujui. Package baru: {$planName}";
        } else {
            $message = "Permintaan Package Anda ke {$planName} ditolak.";
        }

        return [
            'message' => $message,
            'request_id' => $this->PackageRequest?->id,
            'plan_name' => $planName,
            'status' => $this->status,
        ];
    }
}
