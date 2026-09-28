<?php

namespace App\Enums;

enum TtxSessionInjectStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Locked = 'locked';
}
