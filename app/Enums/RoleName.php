<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Secretary = 'secretaire';
    case Member = 'membre';
}
