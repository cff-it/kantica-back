<?php

namespace App\Enums;

enum SacramentType: string
{
    case Baptism = 'baptism';
    case FirstCommunion = 'first_communion';
    case Confirmation = 'confirmation';
    case Marriage = 'marriage';
}
