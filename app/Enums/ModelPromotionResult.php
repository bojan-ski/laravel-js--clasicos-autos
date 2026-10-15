<?php

namespace App\Enums;

enum ModelPromotionResult: string
{
    case Created = 'created';
    case Linked = 'linked';
    case Reactivated = 'reactivated';
}
