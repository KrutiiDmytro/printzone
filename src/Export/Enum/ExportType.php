<?php

declare(strict_types=1);

namespace App\Export\Enum;

enum ExportType: string
{
    case Products = 'products';
    case Orders = 'orders';
    case Users = 'users';
}
