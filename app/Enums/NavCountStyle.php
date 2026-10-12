<?php

namespace App\Enums;

enum NavCountStyle: string
{
    case Unread = 'unread';
    case Queue = 'queue';
    case Selected = 'selected';
}
