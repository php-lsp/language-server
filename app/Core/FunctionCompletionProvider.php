<?php

declare(strict_types=1);

namespace App\Core;

class FunctionCompletionProvider
{
    public function complete(): array
    {
        return get_defined_functions();
    }
}
