<?php

namespace App\Enums;

enum PlatformFeeStatus: string
{
    case Pending = 'pending';

    case Posted = 'posted';

    case Reversed = 'reversed';
}
