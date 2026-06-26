<?php

namespace App\Enums;

enum AssigneeScope: string
{
    case User = 'user';
    case Team = 'team';
    case Department = 'department';
    case Global = 'global';
}
