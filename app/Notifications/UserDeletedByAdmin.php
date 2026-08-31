<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserDeletedByAdmin extends Notification
{
    use Queueable;

    public function __construct(public User $deletedUser) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "User {$this->deletedUser->name} ({$this->deletedUser->email}) telah dihapus oleh admin.",
            'deleted_user_id' => $this->deletedUser->id,
            'deleted_user_name' => $this->deletedUser->name,
            'deleted_user_email' => $this->deletedUser->email,
        ];
    }
}
