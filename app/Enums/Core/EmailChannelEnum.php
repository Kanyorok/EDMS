<?php

namespace App\Enums\Core;

use App\Traits\UsefulEnumTrait;

enum EmailChannelEnum: string
{
    use UsefulEnumTrait;

    case SMTP = 'smtp';

    case InfoBip = 'ifbp';
}
