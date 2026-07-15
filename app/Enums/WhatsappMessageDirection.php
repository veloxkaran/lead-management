<?php

namespace App\Enums;

enum WhatsappMessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
