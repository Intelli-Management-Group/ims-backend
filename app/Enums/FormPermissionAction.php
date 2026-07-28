<?php

namespace App\Enums;

enum FormPermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
}
