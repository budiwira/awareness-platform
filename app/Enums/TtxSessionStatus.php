<?php

namespace App\Enums;

enum TtxSessionStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Debrief = 'debrief';
    case Completed = 'completed';
}
