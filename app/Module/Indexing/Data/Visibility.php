<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

enum Visibility: string
{
    case Public = 'public';
    case Protected = 'protected';
    case Private = 'private';
}
