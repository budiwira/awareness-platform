<?php

namespace App\Enums;

enum TtxSessionRole: string
{
    case Facilitator = 'facilitator';
    case Security = 'security';
    case ItOperations = 'it_operations';
    case PeopleHr = 'people_hr';
    case Communications = 'communications';
    case Management = 'management';
}
