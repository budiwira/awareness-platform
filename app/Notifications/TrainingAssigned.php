<?php

namespace App\Notifications;

use App\Models\TrainingModule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TrainingAssigned extends Notification
{
    use Queueable;

    public function __construct(public TrainingModule $module) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Anda ditugaskan modul training baru: '.$this->module->title,
            'module_id' => $this->module->id,
        ];
    }
}
