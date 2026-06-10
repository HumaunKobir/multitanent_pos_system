<?php

namespace App\Enums;

enum PasswordResetContext: string
{
    case User = 'user';
    case Customer = 'customer';
}
