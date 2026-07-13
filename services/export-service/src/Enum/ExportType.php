<?php

declare(strict_types=1);

namespace App\Enum;

enum ExportType: string
{
    case Products = 'products';
    case Orders = 'orders';
    case Users = 'users';
}
