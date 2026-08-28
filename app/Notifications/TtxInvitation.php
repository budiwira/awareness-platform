<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TtxInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $teamName, public string $exerciseTitle) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Anda diikutsertakan dalam TTX '{$this->exerciseTitle}' sebagai anggota {$this->teamName}.",
        ];
    }
}