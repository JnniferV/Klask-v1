<?php

namespace App\Security;

enum RoleSecurity: string
{
    case ADMIN = 'ADMIN';
    case ACCOMPANYING = 'ACCOMPANYING';
    case STUDENT = 'STUDENT';
}
