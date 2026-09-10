<?php

namespace App\Domain\Conversations\Enums;

enum MessageSenderType: string
{
    case Customer = 'customer';
    case Manager = 'manager';
    case Ai = 'ai';
    case System = 'system';
}
